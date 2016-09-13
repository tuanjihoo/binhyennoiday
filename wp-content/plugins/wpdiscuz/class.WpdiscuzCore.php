<?php

/*
 * Plugin Name: wpDiscuz - Supercharged native comments
 * Description: Better comment system. Wordpress post comments and discussion plugin. Allows your visitors discuss, vote for comments and share.
 * Version: 3.1.3
 * Author: gVectors Team (A. Chakhoyan, G. Zakaryan, H. Martirosyan)
 * Author URI: http://www.gvectors.com/
 * Plugin URI: http://www.gvectors.com/wpdiscuz/
 * Text Domain: wpdiscuz
 * Domain Path: /languages/
 */
if (!defined('ABSPATH')) {
    exit();
}

define('WPDISCUZ_DS', DIRECTORY_SEPARATOR);
define('WPDISCUZ_DIR_PATH', dirname(__FILE__));
define('WPDISCUZ_DIR_NAME', basename(WPDISCUZ_DIR_PATH));

include_once 'utils/interface.WpDiscuzConstants.php';
include_once 'options/class.WpdiscuzOptions.php';
include_once 'options/class.WpdiscuzOptionsSerialized.php';
include_once 'utils/class.WpdiscuzHelper.php';
include_once 'utils/class.WpdiscuzEmailHelper.php';
include_once 'utils/class.WpdiscuzOptimizationHelper.php';
include_once 'manager/class.WpdiscuzDBManager.php';
include_once 'includes/class.WpdiscuzCss.php';
include_once 'templates/comment/class.WpdiscuzWalker.php';

class WpdiscuzCore implements WpDiscuzConstants {

    public $helper;
    public $dbManager;
    public $optionsSerialized;
    private $css;
    private $options;
    private $emailHelper;
    private $wpdiscuzWalker;
    private $optimizationHelper;
    private $commentsArgs;

    public function __construct() {
        $this->dbManager = new WpdiscuzDBManager();
        $this->optionsSerialized = new WpdiscuzOptionsSerialized($this->dbManager);
        $this->options = new WpdiscuzOptions($this->optionsSerialized, $this->dbManager);
        $this->helper = new WpdiscuzHelper($this->optionsSerialized, $this->dbManager);
        $this->emailHelper = new WpdiscuzEmailHelper($this->optionsSerialized, $this->dbManager);
        $this->optimizationHelper = new WpdiscuzOptimizationHelper($this->optionsSerialized, $this->dbManager, $this->emailHelper);
        $this->css = new WpdiscuzCss($this->optionsSerialized);
        $this->wpdiscuzWalker = new WpdiscuzWalker($this->helper, $this->optimizationHelper, $this->dbManager, $this->optionsSerialized);
        register_activation_hook(__FILE__, array($this->dbManager, 'dbCreateTables'));
        register_activation_hook(__FILE__, array($this->helper, 'scheduleTask'));
        register_deactivation_hook(__FILE__, array($this->helper, 'unScheduleTask'));
        add_action('wpdiscuzRemoveOldFiles', array(&$this->helper, 'removeOldFiles'));
        add_action('wp_head', array(&$this, 'initCurrentPostType'));
        add_action('wp_head', array(&$this->css, 'initCustomCss'));

        add_action('plugins_loaded', array(&$this, 'wpdiscuzTextDomain'));
        add_action('admin_init', array(&$this, 'pluginNewVersion'), 2);
        add_action('admin_enqueue_scripts', array(&$this, 'adminPageStylesScripts'), 2315);
        add_action('wp_enqueue_scripts', array(&$this, 'frontEndStylesScripts'));
        add_action('admin_menu', array(&$this, 'addPluginOptionsPage'), -297);
        if (!$this->optionsSerialized->captchaShowHide || $this->optionsSerialized->captchaShowHideForMembers) {
            add_action('wp_ajax_generateCaptcha', array(&$this->helper, 'generateCaptcha'));
            add_action('wp_ajax_nopriv_generateCaptcha', array(&$this->helper, 'generateCaptcha'));
        }
        add_action('wp_ajax_loadMoreComments', array(&$this, 'loadMoreComments'));
        add_action('wp_ajax_nopriv_loadMoreComments', array(&$this, 'loadMoreComments'));
        add_action('wp_ajax_voteOnComment', array(&$this, 'voteOnComment'));
        add_action('wp_ajax_nopriv_voteOnComment', array(&$this, 'voteOnComment'));
        add_action('wp_ajax_wpdiscuzSorting', array(&$this, 'wpdiscuzSorting'));
        add_action('wp_ajax_nopriv_wpdiscuzSorting', array(&$this, 'wpdiscuzSorting'));
        add_action('wp_ajax_addComment', array(&$this, 'addComment'));
        add_action('wp_ajax_nopriv_addComment', array(&$this, 'addComment'));
        add_action('wp_ajax_getSingleComment', array(&$this, 'getSingleComment'));
        add_action('wp_ajax_nopriv_getSingleComment', array(&$this, 'getSingleComment'));
        add_action('wp_ajax_addSubscription', array(&$this->emailHelper, 'addSubscription'));
        add_action('wp_ajax_nopriv_addSubscription', array(&$this->emailHelper, 'addSubscription'));
        add_action('wp_ajax_checkNotificationType', array(&$this->emailHelper, 'checkNotificationType'));
        add_action('wp_ajax_nopriv_checkNotificationType', array(&$this->emailHelper, 'checkNotificationType'));
        add_action('wp_ajax_redirect', array(&$this, 'redirect'));
        add_action('wp_ajax_nopriv_redirect', array(&$this, 'redirect'));
        add_action('admin_post_clearChildrenData', array(&$this->optimizationHelper, 'clearChildrenData'));
        add_action('wp_insert_comment', array(&$this->optimizationHelper, 'addCommentToTree'), 2689, 2);
        add_action('transition_comment_status', array(&$this->optimizationHelper, 'statusEventHandler'), 265, 3);
        add_action('delete_comment', array(&$this->optimizationHelper, 'initSubComments'), 266);
        add_action('deleted_comment', array(&$this->optimizationHelper, 'deleteCommentFromTree'), 267);
        $plugin = plugin_basename(__FILE__);
        add_filter("plugin_action_links_$plugin", array(&$this, 'addPluginSettingsLink'));
        add_filter('comments_clauses', array(&$this, 'getCommentsArgs'));
        if ($this->optionsSerialized->commentEditableTime) {
            add_action('wp_ajax_editComment', array(&$this, 'editComment'));
            add_action('wp_ajax_nopriv_editComment', array(&$this, 'editComment'));
            add_action('wp_ajax_saveEditedComment', array(&$this, 'saveEditedComment'));
            add_action('wp_ajax_nopriv_saveEditedComment', array(&$this, 'saveEditedComment'));
        }
        if ($this->optionsSerialized->commentListUpdateType) {
            add_action('wp_ajax_liveUpdate', array(&$this, 'liveUpdate'));
            add_action('wp_ajax_nopriv_liveUpdate', array(&$this, 'liveUpdate'));
            add_action('wp_ajax_updateOnClick', array(&$this, 'updateOnClick'));
            add_action('wp_ajax_nopriv_updateOnClick', array(&$this, 'updateOnClick'));
        }

        if ($this->optionsSerialized->commentReadMoreLimit) {
            add_action('wp_ajax_readMore', array(&$this, 'readMore'));
            add_action('wp_ajax_nopriv_readMore', array(&$this, 'readMore'));
        }

        add_action('wp_loaded', array(&$this, 'addNewRoles'));
    }

    public function wpdiscuzTextDomain() {
        load_plugin_textdomain('wpdiscuz', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    public function liveUpdate() {
        $commentData = filter_input(INPUT_POST, 'wpdiscuzAjaxData');
        if ($commentData) {
            $this->updateAutomatically($commentData);
        }
    }

    private function updateAutomatically($commentData) {
        global $current_user;
        get_currentuserinfo();
        $messageArray = array();
        parse_str($commentData);
        $postId = intval(trim($postId));
        $loadLastCommentId = intval(trim($loadLastCommentId));
        $visibleCommentIds = trim($visibleCommentIds, ',');
        $email = $current_user->ID ? $current_user->user_email : trim($email);
        if ($visibleCommentIds && $postId && $loadLastCommentId) {
            $lastCommentId = $this->dbManager->getLastCommentId($postId);
            if ($lastCommentId > $loadLastCommentId) {
                $messageArray['code'] = 1;
                $messageArray['loadLastCommentId'] = $lastCommentId;
                $commentListArgs = $this->getCommentListArgs($postId);
                $commentListArgs['new_loaded_class'] = 'wc-new-loaded-comment';
                $commentListArgs['current_user'] = $current_user;
                if ($this->optionsSerialized->commentListUpdateType == 1) {
                    $visibleCommentIds = explode(',', $visibleCommentIds);
                    $newCommentIds = $this->dbManager->getNewCommentIds($postId, $loadLastCommentId, $email);
                    $messageArray['message'] = array();
                    foreach ($newCommentIds as $newCommentId) {
                        $comment = get_comment($newCommentId);
                        if (($comment->comment_parent && (in_array($comment->comment_parent, $visibleCommentIds) || in_array($comment->comment_parent, $newCommentIds))) || !$comment->comment_parent) {
                            $commentHtml = wp_list_comments($commentListArgs, array($comment));
                            $commentObject = array('comment_parent' => $comment->comment_parent, 'comment_html' => $commentHtml);
                            if ($comment->comment_parent) {
                                array_push($messageArray['message'], $commentObject);
                            } else {
                                array_unshift($messageArray['message'], $commentObject);
                            }
                        }
                    }
                } else {
                    $authorComments = $this->dbManager->getAuthorVisibleComments($visibleCommentIds, $email);
                    $newCommentIds = $this->dbManager->getNewCommentIds($postId, $loadLastCommentId, $email);
                    $messageArray['message']['author_replies'] = array();
                    $messageArray['message']['comments'] = array();
                    foreach ($newCommentIds as $newCommentId) {
                        $comment = get_comment($newCommentId);
                        if ($this->optimizationHelper->isReplyInAuthorTree($comment->comment_ID, $authorComments)) { // if is in author tree add as reply
                            $messageArray['message']['author_replies'][] = $newCommentId;
                        } else { // add as new comment
                            if ($comment->comment_parent) {
                                array_push($messageArray['message']['comments'], $newCommentId);
                            } else {
                                array_unshift($messageArray['message']['comments'], $newCommentId);
                            }
                        }
                    }
                    asort($messageArray['message']['author_replies']);
                }
                $messageArray['wc_all_comments_count_new'] = $this->dbManager->getCommentsCount($postId);
            } else {
                $messageArray['code'] = 0;
            }
            wp_die(json_encode($messageArray));
        }
        wp_die();
    }

    public function updateOnClick() {
        $commentData = filter_input(INPUT_POST, 'wpdiscuzAjaxData');
        if ($commentData) {
            global $current_user;
            get_currentuserinfo();
            parse_str($commentData);
            if ($postId && $newCommentIds) {
                $messageArray['code'] = 1;
                $newCommentIds = explode(',', trim($newCommentIds, ','));
                $postId = trim(intval($postId));
                $commentListArgs = $this->getCommentListArgs($postId);
                $commentListArgs['new_loaded_class'] = 'wc-new-loaded-comment';
                $commentListArgs['current_user'] = $current_user;
                $messageArray['message'] = array();
                foreach ($newCommentIds as $newCommentId) {
                    $comment = get_comment($newCommentId);
                    $commentHtml = wp_list_comments($commentListArgs, array($comment));
                    $commentObject = array('comment_parent' => $comment->comment_parent, 'comment_html' => $commentHtml);
                    $messageArray['message'][] = $commentObject;
                }
                wp_die(json_encode($messageArray));
            }
        }
        wp_die();
    }

    public function addComment() {
        $messageArray = array();
        $commentData = filter_input(INPUT_POST, 'wpdiscuzAjaxData');
        $isAnonymous = false;
        if ($commentData) {
            parse_str($commentData);
            $postId = isset($postId) ? intval(trim($postId)) : 0;
            if (function_exists('zerospam_get_key') && isset($wpdiscuz_zs)) {
                $_POST['zerospam_key'] = $wpdiscuz_zs == md5(zerospam_get_key()) ? zerospam_get_key() : '';
            }
            if (wp_verify_nonce($wpdiscuz_comment_form_nonce, self::ACTION_FORM_NONCE) && isset($wpdiscuz_unique_id) && $wpdiscuz_unique_id && $postId) {
                $wpdiscuz_unique_id = filter_var($wpdiscuz_unique_id);
                $wc_comment_depth = isset($wc_comment_depth) && intval($wc_comment_depth) ? $wc_comment_depth : 1;
                $isInSameContainer = '1';
                global $current_user;
                get_currentuserinfo();
                if ($wc_comment_depth > $this->optionsSerialized->wordpressThreadCommentsDepth) {
                    $wc_comment_depth = $this->optionsSerialized->wordpressThreadCommentsDepth;
                    $isInSameContainer = '0';
                } else if (!$this->optionsSerialized->wordpressThreadComments) {
                    $isInSameContainer = '0';
                }
                $notification_type = isset($wpdiscuz_notification_type) ? $wpdiscuz_notification_type : '';
                if ($this->helper->isShowCaptcha($current_user->ID)) {
                    $key = isset($cnonce) ? substr($cnonce, self::CAPTCHA_LENGTH) : '';
                    $fileName = isset($fileName) ? substr($fileName, 0, strlen($fileName) - 4) : '';
                    $captcha = isset($wc_captcha) ? $wc_captcha : '';
                    if (!($this->helper->checkCaptcha($key, $fileName, $captcha))) {
                        $messageArray['code'] = 'wc_invalid_captcha';
                        wp_die(json_encode($messageArray));
                    }
                }

                $website_url = '';
                if ($current_user->ID) {
                    $user_id = $current_user->ID;
                    $user = $current_user;
                    $name = $current_user->display_name;
                    $email = $current_user->user_email;
                } else {
                    $user_id = 0;
                    $name = isset($wc_name) ? filter_var($wc_name) : '';
                    $email = isset($wc_email) ? trim($wc_email) : '';
                    $website_url = isset($wc_website) ? trim($wc_website) : '';

                    if ($website_url != '' && (strpos($website_url, 'http://') !== '' && strpos($website_url, 'http://') !== 0) && (strpos($website_url, 'https://') !== '' && strpos($website_url, 'https://') !== 0)) {
                        $website_url = 'http://' . $website_url;
                    }
                    if (!$this->optionsSerialized->isNameFieldRequired) {
                        $name = !($name) ? __('Anonymous', 'wpdiscuz') : $name;
                    }
                    if (!$this->optionsSerialized->isEmailFieldRequired) {
                        if (!$email) {
                            $email = 'anonymous_' . md5(uniqid() . time()) . '@example.com';
                            $isAnonymous = true;
                        }
                    }
                }

                if ($website_url != '' && (filter_var($website_url, FILTER_VALIDATE_URL) === false)) {
                    $messageArray['code'] = 'wc_error_url_text';
                    wp_die(json_encode($messageArray));
                }

                if ($email != '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    $messageArray['code'] = 'wc_error_email_text';
                    wp_die(json_encode($messageArray));
                }

                $comment_content = wp_kses(trim($wc_comment), $this->helper->wc_allowed_tags);
                $commentMinLength = intval($this->optionsSerialized->commentTextMinLength);
                $commentMaxLength = intval($this->optionsSerialized->commentTextMaxLength);
                $contentLength = function_exists('mb_strlen') ? mb_strlen($comment_content) : strlen($comment_content);
                if ($commentMinLength > 0 && $contentLength < $commentMinLength) {
                    $messageArray['code'] = 'wc_msg_comment_text_min_length';
                    wp_die(json_encode($messageArray));
                }

                if ($commentMaxLength > 0 && $contentLength > $commentMaxLength) {
                    $messageArray['code'] = 'wc_msg_comment_text_max_length';
                    wp_die(json_encode($messageArray));
                }

                if ($name && $email && $comment_content) {
                    $author_ip = $this->helper->getRealIPAddr();
                    $uid_data = $this->helper->getUIDData($wpdiscuz_unique_id);
                    $comment_parent = $uid_data[0];
                    $wc_user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
                    $new_commentdata = array(
                        'user_id' => $user_id,
                        'comment_post_ID' => $postId,
                        'comment_parent' => $comment_parent,
                        'comment_author' => $name,
                        'comment_author_email' => $email,
                        'comment_content' => $comment_content,
                        'comment_author_url' => $website_url,
                        'comment_author_IP' => $author_ip,
                        'comment_agent' => $wc_user_agent,
                        'comment_type' => ''
                    );

                    $new_comment_id = wp_new_comment($new_commentdata);
                    $newComment = get_comment($new_comment_id);
                    $held_moderate = 1;
                    if ($newComment->comment_approved) {
                        $held_moderate = 0;
                    }
                    if ($notification_type == WpdiscuzCore::SUBSCRIPTION_POST && class_exists('Prompt_Comment_Form_Handling') && $this->optionsSerialized->usePostmaticForCommentNotification) {
                        $_POST[Prompt_Comment_Form_Handling::SUBSCRIBE_CHECKBOX_NAME] = 1;
                        Prompt_Comment_Form_Handling::handle_form($new_comment_id, $newComment->comment_approved);
                    } else if (!$isAnonymous && $notification_type) {
                        if ($current_user->ID && $this->optionsSerialized->disableMemberConfirm) {
                            $this->dbManager->addEmailNotification($new_comment_id, $postId, $email, self::SUBSCRIPTION_COMMENT, 1);
                        } else {
                            $this->dbManager->addEmailNotification($new_comment_id, $postId, $email, self::SUBSCRIPTION_COMMENT);
                            $this->emailHelper->confirmEmailSender($postId, $email);
                        }
                    }
                    $messageArray['code'] = $wpdiscuz_unique_id;
                    $messageArray['redirect'] = $this->optionsSerialized->redirectPage;
                    $messageArray['new_comment_id'] = $new_comment_id;
                    $messageArray['user_name'] = $name;
                    $messageArray['user_email'] = $email;
                    $messageArray['is_main'] = $comment_parent ? 0 : 1;
                    $messageArray['held_moderate'] = $held_moderate;
                    $messageArray['is_in_same_container'] = $isInSameContainer;
                    $messageArray['wc_all_comments_count_new'] = $this->dbManager->getCommentsCount($postId);
                    $commentListArgs = $this->getCommentListArgs($postId);
                    $commentListArgs['current_user'] = $current_user;
                    $commentListArgs['addComment'] = $wc_comment_depth;
                    $messageArray['message'] = wp_list_comments($commentListArgs, array($newComment));
                } else {
                    $messageArray['code'] = 'wc_invalid_field';
                }
            } else {
                $messageArray['code'] = 'wc_invalid_field';
            }
        } else {
            $messageArray['code'] = 'wc_msg_required_fields';
        }
        wp_die(json_encode($messageArray));
    }

    /**
     * get comment text from db
     */
    public function editComment() {
        $commentData = filter_input(INPUT_POST, 'wpdiscuzAjaxData');
        $message_array = array();
        if ($commentData) {
            parse_str($commentData);
            $current_user = wp_get_current_user();
            $commentId = intval($commentId);
            if ($commentId) {
                $comment = get_comment($commentId);
                if (current_user_can('edit_comment', $comment->comment_ID)) {
                    $message_array['code'] = 1;
                    $message_array['message'] = $comment->comment_content;
                } else {
                    $isInRange = $this->helper->isContentInRange($comment->comment_content);
                    $isEditable = $this->optionsSerialized->commentEditableTime == 'unlimit' ? true && $isInRange : $this->helper->isCommentEditable($comment) && $isInRange;
                    if (isset($current_user) && $comment->user_id == $current_user->ID && $isEditable) {
                        $message_array['code'] = 1;
                        $message_array['message'] = $comment->comment_content;
                    } else {
                        $message_array['code'] = 'wc_comment_edit_not_possible';
                    }
                }
            } else {
                $message_array['code'] = 'wc_comment_edit_not_possible';
            }
        } else {
            $message_array['code'] = 'wc_comment_edit_not_possible';
        }
        wp_die(json_encode($message_array));
    }

    /**
     * save edited comment via ajax
     */
    public function saveEditedComment() {
        $message_array = array();
        $commentData = filter_input(INPUT_POST, 'wpdiscuzAjaxData');
        if ($commentData) {
            parse_str($commentData);
            $commentId = intval($commentId);
            $comment = get_comment($commentId);
            $current_user = wp_get_current_user();
            $trimmedCommentContent = trim($commentContent);
            $isCurrentUserCanEdit = isset($current_user) && ($comment->user_id == $current_user->ID || current_user_can('edit_comment', $comment->comment_ID));
            if ($trimmedCommentContent && $this->helper->isContentInRange($trimmedCommentContent) && $isCurrentUserCanEdit) {
                if ($trimmedCommentContent != $comment->comment_content) {
                    $commentContent = wp_kses($commentContent, $this->helper->wc_allowed_tags);
                    $author_ip = $this->helper->getRealIPAddr();
                    $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
                    $commentarr = array(
                        'comment_ID' => $commentId,
                        'comment_content' => apply_filters('pre_comment_content', $commentContent),
                        'comment_author_IP' => apply_filters('pre_comment_user_ip', $author_ip),
                        'comment_agent' => apply_filters('pre_comment_user_agent', $userAgent),
                        'comment_approved' => $comment->comment_approved
                    );
                    if (wp_update_comment($commentarr)) {
                        $uniqueId = $comment->comment_ID . '_' . $comment->comment_parent;
                        $message_array['code'] = 1;
                        if ($this->optionsSerialized->commentReadMoreLimit && count(explode(' ', strip_tags($commentContent))) > $this->optionsSerialized->commentReadMoreLimit) {
                            $commentContent = $this->helper->getCommentExcerpt($commentContent, $uniqueId);
                        }
                        $commentContent = apply_filters('comment_text', $commentContent);
                        $commentContent = $this->helper->makeClickable($commentContent);
                        $message_array['message'] = $commentContent;
                    } else {
                        $message_array['code'] = 'wc_comment_not_updated';
                    }
                } else {
                    $message_array['code'] = 'wc_comment_not_edited';
                }
            } else {
                $message_array['code'] = 'wc_comment_edit_not_possible';
            }
        } else {
            $message_array['code'] = 'wc_comment_edit_not_possible';
        }
        wp_die(json_encode($message_array));
    }

    public function getSingleComment() {
        global $current_user;
        get_currentuserinfo();
        $messageArray = array();
        $commentData = filter_input(INPUT_POST, 'wpdiscuzAjaxData');
        if ($commentData) {
            parse_str($commentData);
            $commentId = intval(trim($commentId));
            $postId = intval(trim($postId));
            if ($commentId && $postId) {
                $parentComment = $this->optimizationHelper->getCommentRoot($commentId);
                $tree = array();
                $tree = $this->optimizationHelper->getTreeByParentId($parentComment->comment_ID, $tree);
                $this->commentsArgs = $this->getDefaultCommentsArgs();
                $this->commentsArgs['wc_comments'] = array_merge(array($parentComment->comment_ID), $tree);
                $comments = get_comments($this->commentsArgs);
                $commentListArgs = $this->getCommentListArgs($postId);
                $commentListArgs['isSingle'] = true;
                $commentListArgs['new_loaded_class'] = 'wc-new-loaded-comment';
                $commentListArgs['current_user'] = $current_user;
                $messageArray['message'] = wp_list_comments($commentListArgs, $comments);
            }
            $this->commentsArgs['caller'] = '';
            wp_die(json_encode($messageArray));
        }
    }

    /**
     * redirect first commenter to the selected page from options
     */
    public function redirect() {
        $messageArray = array();
        if (isset($_POST['wpdiscuzAjaxData'])) {
            parse_str($_POST['wpdiscuzAjaxData']);
            $commentId = intval(trim($commentId));
            if ($this->optionsSerialized->redirectPage && $commentId) {
                $comment = get_comment($commentId);
                if ($comment->comment_ID) {
                    $userCommentCount = get_comments(array('author_email' => $comment->comment_author_email, 'count' => true));
                    if ($userCommentCount == 1) {
                        $messageArray['code'] = 1;
                        $messageArray['redirect_to'] = get_permalink($this->optionsSerialized->redirectPage);
                    }
                }
            }
        }
        $this->commentsArgs['caller'] = '';
        wp_die(json_encode($messageArray));
    }

    public function loadMoreComments() {
        if (isset($_POST['wpdiscuzAjaxData'])) {
            parse_str($_POST['wpdiscuzAjaxData']);
            $postId = intval(trim($postId));
            $limit = ($this->optionsSerialized->commentListLoadType == 1) ? 0 : $this->optionsSerialized->wordpressCommentPerPage;
            $args = array('limit' => $limit);
            $orderBy = trim($orderBy);
            $args['offset'] = isset($offset) && $offset ? intval($offset) * $this->optionsSerialized->wordpressCommentPerPage : 0;
            if ($orderBy == 'by_vote') {
                $args['orderby'] = $orderBy;
            } else {
                $order = trim($order);
                $args['order'] = $order ? $order : $this->optionsSerialized->wordpressCommentOrder;
                $args['last_parent_id'] = intval($lastParentId);
            }
            $args['post_id'] = $postId;
            $data = $this->getWPComments($args);
            wp_die(json_encode($data));
        }
    }

    public function voteOnComment() {
        if ($this->optionsSerialized->votingButtonsShowHide) {
            wp_die();
        }
        $messageArray = array();
        $commentId = '';
        $isUserLoggedIn = is_user_logged_in();
        if (!$this->optionsSerialized->isGuestCanVote && !$isUserLoggedIn) {
            $messageArray['code'] = 'wc_login_to_vote';
            wp_die(json_encode($messageArray));
        }
        if (isset($_POST['wpdiscuzAjaxData'])) {
            parse_str($_POST['wpdiscuzAjaxData']);
            $commentId = intval($commentId);
            $voteType = intval($voteType);
            if ($commentId && $voteType) {
                $userIdOrIp = $isUserLoggedIn ? get_current_user_id() : $this->helper->getRealIPAddr();
                $isUserVoted = $this->dbManager->isUserVoted($userIdOrIp, $commentId);
                $comment = get_comment($commentId);
                if (!$isUserLoggedIn && $comment->comment_author_IP == $userIdOrIp) {
                    $messageArray['code'] = 'wc_deny_voting_from_same_ip';
                    wp_die(json_encode($messageArray));
                }
                if ($comment->user_id == $userIdOrIp) {
                    $messageArray['code'] = 'wc_self_vote';
                    wp_die(json_encode($messageArray));
                }

                if ($isUserVoted != '') {
                    $vote = intval($isUserVoted) + $voteType;
                    if ($vote >= -1 && $vote <= 1) {
                        $this->dbManager->updateVoteType($userIdOrIp, $commentId, $vote);
                        $voteCount = intval(get_comment_meta($commentId, self::META_KEY_VOTES, true)) + $voteType;
                        update_comment_meta($commentId, self::META_KEY_VOTES, '' . $voteCount);
                        $messageArray['code'] = 1;
                    } else {
                        $messageArray['code'] = 'wc_vote_only_one_time';
                    }
                } else {
                    $this->dbManager->addVoteType($userIdOrIp, $commentId, $voteType, intval($isUserLoggedIn));
                    $voteCount = intval(get_comment_meta($commentId, self::META_KEY_VOTES, true)) + $voteType;
                    update_comment_meta($commentId, self::META_KEY_VOTES, '' . $voteCount);
                    $messageArray['code'] = 1;
                }
            } else {
                $messageArray['code'] = 'wc_voting_error';
            }
        } else {
            $messageArray['code'] = 'wc_voting_error';
        }
        wp_die(json_encode($messageArray));
    }

    public function wpdiscuzSorting() {
        $messageArray = array();
        if (isset($_POST['wpdiscuzAjaxData'])) {
            parse_str($_POST['wpdiscuzAjaxData']);
            $postId = intval(trim($postId));
            $orderBy = trim($orderBy);
            $order = trim($order);
            if ($postId && $orderBy && $order) {
                $args = array('order' => $order, 'post_id' => $postId);
                if (in_array($orderBy, array('by_vote', 'comment_date_gmt'))) {
                    $args['orderby'] = $orderBy;
                } else {
                    $args['orderby'] = 'comment_date_gmt';
                }
                $data = $this->getWPComments($args);
                $messageArray['code'] = 1;
                $messageArray['loadCount'] = 1;
                $messageArray['last_parent_id'] = $data['last_parent_id'];
                $messageArray['is_show_load_more'] = $data['is_show_load_more'];
                $messageArray['message'] = $data['comment_list'];
                wp_die(json_encode($messageArray));
            }
        }
        wp_die();
    }

    /**
     * loads the comment content on click via ajax
     */
    public function readMore() {
        $messageArray = array();
        if (isset($_POST['wpdiscuzAjaxData'])) {
            parse_str($_POST['wpdiscuzAjaxData']);
            $commentId = intval(trim($commentId));
            $comment = get_comment($commentId);
            if ($comment) {
                $commentContent = wp_kses($comment->comment_content, $this->helper->wc_allowed_tags);
                $commentContent = apply_filters('comment_text', $commentContent);
                $commentContent = $this->helper->makeClickable($commentContent);
                $messageArray['code'] = 1;
                $messageArray['message'] = $commentContent;
                wp_die(json_encode($messageArray));
            }
        }
        $messageArray['code'] = 0;
        $messageArray['message'] = 'error';
        wp_die(json_encode($messageArray));
    }

    /**
     * get comments by comment type
     */
    public function getWPComments($args = array()) {
        global $post, $current_user;
        get_currentuserinfo();
        $postId = isset($post) ? $post->ID : '';
        $defaults = $this->getDefaultCommentsArgs($postId);
        $this->commentsArgs = wp_parse_args($args, $defaults);
        $commentData = array();
        $commentListArgs = $this->getCommentListArgs($this->commentsArgs['post_id']);
        $commentList = $this->_getWPComments($commentListArgs, $commentData);
        $commentListArgs['current_user'] = $current_user;
        $wcWpComments = wp_list_comments($commentListArgs, $commentList);
        $commentData['comment_list'] = $wcWpComments;
        return $commentData;
    }

    private function _getWPComments(&$commentListArgs, &$commentData) {
        if (!$this->optionsSerialized->wordpressIsPaginate) {
            $this->commentsArgs['wc_comments'] = $this->dbManager->getCommentList($this->commentsArgs);
            $commentData['last_parent_id'] = $this->commentsArgs['wc_comments'] ? $this->commentsArgs['wc_comments'][count($this->commentsArgs['wc_comments']) - 1] : 0;
            if ($this->commentsArgs['is_threaded']) {
                $commentmetaIds = $this->optimizationHelper->getCommentListByParentIds($this->commentsArgs['wc_comments'], $this->commentsArgs['post_id']);
                $this->commentsArgs['wc_comments'] = array_merge($this->commentsArgs['wc_comments'], $commentmetaIds);
            }
            $commentListArgs['page'] = 1;
            $commentListArgs['last_parent_id'] = $commentData['last_parent_id'];
            $commentData['is_show_load_more'] = $this->helper->isShowLoadMore($commentData['last_parent_id'], $this->commentsArgs);
        }
        $commentList = get_comments($this->commentsArgs);
        $this->commentsArgs['caller'] = '';
        return $commentList;
    }

    /**
     * add comments clauses
     * add new orderby clause when sort type is vote and wordpress commnts order is older (ASC)
     */
    public function getCommentsArgs($args) {
        global $wpdb;
        if ($this->commentsArgs['caller'] === 'wpdiscuz' && $this->commentsArgs['wc_comments']) {
            $comments = implode(',', $this->commentsArgs['wc_comments']);
            $commentIds = trim($comments, ',');
            $args['join'] .= "INNER JOIN " . $wpdb->commentmeta . " ON " . $wpdb->comments . ".comment_ID = " . $wpdb->commentmeta . ".comment_id";
            $args['where'] .= " AND " . $wpdb->comments . ".comment_ID IN ($commentIds) AND ( " . $wpdb->commentmeta . ".meta_key = 'wpdiscuz_votes' ) ";
            $orderby = '';
            if ($this->commentsArgs['orderby'] == 'by_vote') {
                $orderby = $wpdb->commentmeta . ".meta_value+0 DESC, ";
            }
            $args['orderby'] = $orderby . $wpdb->comments . ".comment_date_gmt ";
            $args['orderby'] .= isset($args['order']) ? '' : $this->commentsArgs['order'];
        }
        return $args;
    }

    private function getDefaultCommentsArgs($postId = 0) {
        $args = array(
            'caller' => 'wpdiscuz',
            'post_id' => $postId,
            'offset' => 0,
            'last_parent_id' => 0,
            'orderby' => 'comment_date_gmt',
            'order' => $this->optionsSerialized->wordpressCommentOrder,
            'date_order' => $this->optionsSerialized->wordpressCommentOrder,
            'limit' => $this->optionsSerialized->wordpressCommentPerPage,
            'is_threaded' => $this->optionsSerialized->wordpressThreadComments,
            'status' => 'approve',
            'wc_comments' => ''
        );
        return $args;
    }

    /**
     * register options page for plugin
     */
    public function addPluginOptionsPage() {
        add_submenu_page('edit-comments.php', 'wpDiscuz Settings', 'wpDiscuz Settings', 'manage_options', 'wpdiscuz_options_page', array(&$this->options, 'mainOptionsForm'));
        if (!$this->optionsSerialized->isUsePoMo) {
            add_submenu_page('edit-comments.php', 'wpDiscuz Phrases', 'wpDiscuz Phrases', 'manage_options', 'wpdiscuz_phrases_page', array(&$this->options, 'phrasesOptionsForm'));
        }
    }

    /**
     * Scripts and styles registration on administration pages
     */
    public function adminPageStylesScripts() {
        wp_register_style('wpdiscuz-cp-index-css', plugins_url(WPDISCUZ_DIR_NAME . '/assets/third-party/colorpicker/css/index.css'));
        wp_enqueue_style('wpdiscuz-cp-index-css');
        wp_register_style('wpdiscuz-cp-compatibility-css', plugins_url(WPDISCUZ_DIR_NAME . '/assets/third-party/colorpicker/css/compatibility.css'));
        wp_enqueue_style('wpdiscuz-cp-compatibility-css');
        wp_register_script('wpdiscuz-cp-colors-js', plugins_url(WPDISCUZ_DIR_NAME . '/assets/third-party/colorpicker/js/colors.js'), array('jquery'), '1.0.0', false);
        wp_enqueue_script('wpdiscuz-cp-colors-js');
        wp_register_script('wpdiscuz-cp-colorpicker-js', plugins_url(WPDISCUZ_DIR_NAME . '/assets/third-party/colorpicker/js/jqColorPicker.min.js'), array('jquery'), '1.0.0', false);
        wp_enqueue_script('wpdiscuz-cp-colorpicker-js');
        wp_register_script('wpdiscuz-cp-index-js', plugins_url(WPDISCUZ_DIR_NAME . '/assets/third-party/colorpicker/js/index.js'), array('jquery'), '1.0.0', false);
        wp_enqueue_script('wpdiscuz-cp-index-js');
        wp_register_style('wpdiscuz-easy-responsive-tabs-css', plugins_url(WPDISCUZ_DIR_NAME . '/assets/third-party/easy-responsive-tabs/css/easy-responsive-tabs.min.css'), true);
        wp_enqueue_style('wpdiscuz-easy-responsive-tabs-css');
        wp_register_script('wpdiscuz-easy-responsive-tabs-js', plugins_url(WPDISCUZ_DIR_NAME . '/assets/third-party/easy-responsive-tabs/js/easy-responsive-tabs.js'), array('jquery'), '1.0.0', true);
        wp_enqueue_script('wpdiscuz-easy-responsive-tabs-js');
        wp_register_style('wpdiscuz-options-css', plugins_url(WPDISCUZ_DIR_NAME . '/assets/css/options-css.min.css'));
        wp_enqueue_style('wpdiscuz-options-css');
        $realLastCommentId = $this->dbManager->getLastCommentId();
        wp_register_script('wpdiscuz-options-js', plugins_url(WPDISCUZ_DIR_NAME . '/assets/js/wpdiscuz-options.min.js'), array('jquery'));
        wp_localize_script('wpdiscuz-options-js', 'admin_options_obj', array('lastCommentId' => $realLastCommentId));
        wp_enqueue_script('wpdiscuz-options-js');
        wp_enqueue_script('thickbox');
        wp_register_script('wpdiscuz-jquery-cookie', plugins_url(WPDISCUZ_DIR_NAME . '/assets/js/jquery.cookie.min.js'), array('jquery'), '1.0.0', true);
        wp_enqueue_script('wpdiscuz-jquery-cookie');
    }

    /**
     * Styles and scripts registration to use on front page
     */
    public function frontEndStylesScripts() {
        global $post;
        if ($post && in_array($post->post_type, $this->optionsSerialized->postTypes) && (is_singular() || is_front_page()) && post_type_supports($post->post_type, 'comments')) {

            $u_agent = $_SERVER['HTTP_USER_AGENT'];
            wp_register_style('wpdiscuz-frontend-css', plugins_url(WPDISCUZ_DIR_NAME . '/assets/css/wpdiscuz.min.css'), null, get_option(self::OPTION_SLUG_VERSION));
            wp_enqueue_style('wpdiscuz-frontend-css');

            if (is_rtl()) {
                wp_register_style('wpdiscuz-frontend-rtl-css', plugins_url(WPDISCUZ_DIR_NAME . '/assets/css/wpdiscuz-rtl.min.css'));
                wp_enqueue_style('wpdiscuz-frontend-rtl-css');
            }

            wp_register_script('wpdiscuz-jquery-ui', plugins_url(WPDISCUZ_DIR_NAME . '/assets/third-party/jquery-ui/jquery-ui.min.js'), array('jquery'), '1.11.2', false);
            wp_enqueue_script('wpdiscuz-jquery-ui');

            if (preg_match('/MSIE/i', $u_agent)) {
                wp_register_script('wpdiscuz-html5-js', plugins_url(WPDISCUZ_DIR_NAME . '/assets/third-party/tooltipster/js/html5.min.js'), array('jquery'), '1.2', false);
                wp_enqueue_script('wpdiscuz-html5-js');
            }

            wp_register_script('wpdiscuz-validator-js', plugins_url(WPDISCUZ_DIR_NAME . '/assets/js/validator.min.js'), array('jquery'), '1.0.0', false);
            wp_enqueue_script('wpdiscuz-validator-js');
            wp_register_style('wpdiscuz-validator-style', plugins_url(WPDISCUZ_DIR_NAME . '/assets/css/fv.min.css'));
            wp_enqueue_style('wpdiscuz-validator-style');
            wp_register_script('wpdiscuz-cookie-js', plugins_url(WPDISCUZ_DIR_NAME . '/assets/js/jquery.cookie.min.js'), array('jquery'), '1.4.1', false);
            wp_enqueue_script('wpdiscuz-cookie-js');
            wp_register_style('wpdiscuz-tooltipster-style', plugins_url(WPDISCUZ_DIR_NAME . '/assets/third-party/tooltipster/css/tooltipster.min.css'));
            wp_enqueue_style('wpdiscuz-tooltipster-style');
            wp_register_script('wpdiscuz-tooltipster-js', plugins_url(WPDISCUZ_DIR_NAME . '/assets/third-party/tooltipster/js/jquery.tooltipster.min.js'), array('jquery'), '1.2', false);
            wp_enqueue_script('wpdiscuz-tooltipster-js');
            wp_register_script('autogrowtextarea-js', plugins_url(WPDISCUZ_DIR_NAME . '/assets/js/jquery.autogrowtextarea.min.js'), array('jquery'), '3.0', false);
            wp_enqueue_script('autogrowtextarea-js');
            $wpdiscuz_js_options = $this->optionsSerialized->getOptionsForJs();
            $wpdiscuz_js_options['wc_post_id'] = $post->ID;
            $wpdiscuz_js_options['loadLastCommentId'] = $this->dbManager->getLastCommentId($post->ID);
            wp_register_script('wpdiscuz-ajax-js', plugins_url(WPDISCUZ_DIR_NAME . '/assets/js/wpdiscuz.min.js'), array('jquery'), get_option(self::OPTION_SLUG_VERSION));
            wp_enqueue_script('wpdiscuz-ajax-js');
            wp_localize_script('wpdiscuz-ajax-js', 'wpdiscuz_ajax_obj', array('url' => admin_url('admin-ajax.php'), 'wpdiscuz_options' => $wpdiscuz_js_options));
        }
    }

    public function pluginNewVersion() {
        $this->dbManager->createEmailNotificationTable();
        $wc_version = (!get_option(self::OPTION_SLUG_VERSION) ) ? '1.0.0' : get_option(self::OPTION_SLUG_VERSION);
        $wc_plugin_data = get_plugin_data(__FILE__);
        if (version_compare($wc_plugin_data['Version'], $wc_version, '>')) {
            $oldOptions = maybe_unserialize(get_option(WpdiscuzCore::OPTION_SLUG_OPTIONS));
            if (isset($oldOptions['wc_comment_list_order'])) {
                update_option('comment_order', $oldOptions['wc_comment_list_order']);
            }
            if (isset($oldOptions['wc_comment_count'])) {
                update_option('comments_per_page', $oldOptions['wc_comment_count']);
            }
            if (isset($oldOptions['wc_load_all_comments'])) {
                $this->optionsSerialized->commentListLoadType = 1;
            }
            $this->addNewOptions();
            $this->addNewPhrases();
            if ($wc_version === '1.0.0') {
                add_option(self::OPTION_SLUG_VERSION, $wc_plugin_data['Version']);
            } else {
                update_option(self::OPTION_SLUG_VERSION, $wc_plugin_data['Version']);
            }
            if (version_compare($wc_version, '2.1.2', '<=') && version_compare($wc_version, '1.0.0', '!=')) {
                $this->dbManager->alterPhrasesTable();
            }

            if (version_compare($wc_version, '2.1.7', '<=') && version_compare($wc_version, '1.0.0', '!=')) {
                $this->dbManager->alterVotingTable();
            }

            if (version_compare($wc_version, '3.0.0', '<=') && version_compare($wc_version, '1.0.0', '!=')) {
                $this->dbManager->alterNotificationTable();
            }
        }
    }

    /**
     * merge old and new options
     */
    private function addNewOptions() {
        $this->optionsSerialized->initOptions(get_option(self::OPTION_SLUG_OPTIONS));
        $wc_new_options = $this->optionsSerialized->toArray();
        update_option(self::OPTION_SLUG_OPTIONS, serialize($wc_new_options));
    }

    /**
     * merge old and new phrases
     */
    private function addNewPhrases() {
        if ($this->dbManager->isPhraseExists('wc_leave_a_reply_text')) {
            $wc_saved_phrases = $this->dbManager->getPhrases();
            $this->optionsSerialized->initPhrases();
            $wc_phrases = $this->optionsSerialized->phrases;
            $wc_new_phrases = array_merge($wc_phrases, $wc_saved_phrases);
            $this->dbManager->updatePhrases($wc_new_phrases);
        }
    }

    // Add settings link on plugin page
    public function addPluginSettingsLink($links) {
        $settingsLink = '<a href="' . admin_url() . 'edit-comments.php?page=wpdiscuz_options_page">' . __('Settings', 'wpdiscuz') . '</a>';
        if (!$this->optionsSerialized->isUsePoMo) {
            $settingsLink .= ' | <a href="' . admin_url() . 'edit-comments.php?page=wpdiscuz_phrases_page">' . __('Phrases', 'wpdiscuz') . '</a>';
        }
        array_unshift($links, $settingsLink);
        return $links;
    }

    public function initCurrentPostType() {
        global $post;
        if ($post && in_array($post->post_type, $this->optionsSerialized->postTypes) && (is_singular() || is_front_page()) && post_type_supports($post->post_type, 'comments')) {
            add_filter('comments_template', array(&$this, 'addCommentForm'), 10);
        }
    }

    public function addCommentForm($file) {
        $file = dirname(__FILE__) . '/templates/comment/comment-form.php';
        return $file;
    }

    private function getCommentListArgs($postId) {
        $postsAuthors = $this->dbManager->getPostsAuthors();
        $post = get_post($postId);
        return array(
            'style' => 'div',
            'echo' => false,
            'isSingle' => false,
            'reverse_top_level' => false,
            'post_author' => $post->post_author,
            'posts_authors' => $postsAuthors,
            'walker' => $this->wpdiscuzWalker,
        );
    }

    public function addNewRoles() {
        global $wp_roles;
        $roles = $wp_roles->roles;
        $roles = apply_filters('editable_roles', $roles);
        foreach ($roles as $roleName => $roleInfo) {
            $this->optionsSerialized->blogRoles[$roleName] = isset($this->optionsSerialized->blogRoles[$roleName]) ? $this->optionsSerialized->blogRoles[$roleName] : '#00B38F';
            if ($roleName == 'administrator') {
                $this->optionsSerialized->phrases['wc_blog_role_' . $roleName] = isset($this->optionsSerialized->phrases['wc_blog_role_' . $roleName]) ? $this->optionsSerialized->phrases['wc_blog_role_' . $roleName] : __('Admin', 'wpdiscuz');
            } elseif ($roleName == 'post_author') {
                $this->optionsSerialized->phrases['wc_blog_role_' . $roleName] = isset($this->optionsSerialized->phrases['wc_blog_role_' . $roleName]) ? $this->optionsSerialized->phrases['wc_blog_role_' . $roleName] : __('Author', 'wpdiscuz');
            } elseif ($roleName == 'editor') {
                $this->optionsSerialized->phrases['wc_blog_role_' . $roleName] = isset($this->optionsSerialized->phrases['wc_blog_role_' . $roleName]) ? $this->optionsSerialized->phrases['wc_blog_role_' . $roleName] : ucfirst(str_replace('_', ' ', $roleName));
            } else {
                $this->optionsSerialized->phrases['wc_blog_role_' . $roleName] = isset($this->optionsSerialized->phrases['wc_blog_role_' . $roleName]) ? $this->optionsSerialized->phrases['wc_blog_role_' . $roleName] : __('Member', 'wpdiscuz');
            }
        }
        $this->optionsSerialized->blogRoles['post_author'] = isset($this->optionsSerialized->blogRoles['post_author']) ? $this->optionsSerialized->blogRoles['post_author'] : '#00B38F';
        $this->optionsSerialized->blogRoles['guest'] = isset($this->optionsSerialized->blogRoles['guest']) ? $this->optionsSerialized->blogRoles['guest'] : '#00B38F';
        $this->optionsSerialized->phrases['wc_blog_role_post_author'] = isset($this->optionsSerialized->phrases['wc_blog_role_post_author']) ? $this->optionsSerialized->phrases['wc_blog_role_post_author'] : __('Author', 'wpdiscuz');
        $this->optionsSerialized->phrases['wc_blog_role_guest'] = isset($this->optionsSerialized->phrases['wc_blog_role_guest']) ? $this->optionsSerialized->phrases['wc_blog_role_guest'] : __('Guest', 'wpdiscuz');
    }

}

$wpdiscuz = new WpdiscuzCore();
