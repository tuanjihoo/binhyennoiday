<?php

/** COMMENTS WALKER */
class WpdiscuzWalker extends Walker_Comment {

    public $tree_type = 'comment';
    public $db_fields = array('parent' => 'comment_parent', 'id' => 'comment_ID');
    private $helper;
    private $optimizationHelper;
    private $dbManager;
    private $optionsSerialized;

    public function __construct($helper, $optimizationHelper, $dbManager, $optionsSerialized) {
        $this->helper = $helper;
        $this->optimizationHelper = $optimizationHelper;
        $this->dbManager = $dbManager;
        $this->optionsSerialized = $optionsSerialized;
    }

    /** START_EL */
    public function start_el(&$output, $comment, $depth = 0, $args = array(), $id = 0) {
        $depth++;
        $GLOBALS['comment_depth'] = $depth;
        $GLOBALS['comment'] = $comment;
        // BEGIN
        $current_user = $args['current_user'];
        $depth = isset($args['addComment']) ? $args['addComment'] : $depth;
        $uniqueId = $comment->comment_ID . '_' . $comment->comment_parent;
        $commentContent = $comment->comment_content;
        $commentWrapperClass = '';
        if ($this->optionsSerialized->commentReadMoreLimit && count(explode(' ', strip_tags($commentContent))) > $this->optionsSerialized->commentReadMoreLimit) {
            $commentContent = $this->helper->getCommentExcerpt($commentContent, $uniqueId);
        }
        $commentContent = wp_kses($commentContent, $this->helper->wc_allowed_tags);
        $commentContent = $this->helper->makeClickable($commentContent);
        $commentContent = apply_filters('comment_text', $commentContent, $comment, $args);
        $commentContent .= $comment->comment_approved == 0 ? '<p class="wc_held_for_moderate">' . $this->optionsSerialized->phrases['wc_held_for_moderate'] . '</p>' : '';
        $hideAvatarStyle = $this->optionsSerialized->wordpressShowAvatars ? '' : 'style = "margin-left : 0;"';
        if ($this->optionsSerialized->wordpressIsPaginate && $comment->comment_parent) {
            $rootComment = $this->optimizationHelper->getCommentRoot($comment->comment_parent);
        }
        if (isset($args['new_loaded_class'])) {
            $commentWrapperClass .= $args['new_loaded_class'] . ' ';
            if ($args['isSingle']) {
                $commentWrapperClass .= ' wpdiscuz_single ';
            } else {
                $depth = $this->optimizationHelper->getCommentDepth($comment->comment_ID);
            }
        }

        if ($comment->user_id) {
            $user = get_user_by('id', $comment->user_id);
            $authorAvatarField = $comment->user_id;
            $profileUrl = in_array($comment->user_id, $args['posts_authors']) ? get_author_posts_url($comment->user_id) : '';
        } else {
            $user = null;
            $authorAvatarField = $comment->comment_author_email;
            $profileUrl = '';
        }

        $commentAuthorUrl = ('http://' == $comment->comment_author_url) ? '' : $comment->comment_author_url;
        $commentAuthorUrl = esc_url($commentAuthorUrl, array('http', 'https'));
        $commentAuthorUrl = apply_filters('get_comment_author_url', $commentAuthorUrl, $comment->comment_ID, $comment);
        if ($user) {
            $commentAuthorUrl = $commentAuthorUrl ? $commentAuthorUrl : $user->user_url;
            if ($user->ID == $args['post_author']) {
                $authorClass = 'wc-blog-post_author';
                $author_title = $this->optionsSerialized->phrases['wc_blog_role_post_author'];
            } else {
                $authorClass = 'wc-blog-' . $user->roles[0];
                $author_title = $this->optionsSerialized->phrases['wc_blog_role_' . $user->roles[0]];
            }
        } else {
            $authorClass = 'wc-blog-guest';
            $author_title = $this->optionsSerialized->phrases['wc_blog_role_guest'];
        }

        if ($this->optionsSerialized->simpleCommentDate) {
            $dateFormat = $this->optionsSerialized->wordpressDateFormat;
            $timeFormat = $this->optionsSerialized->wordpressTimeFormat;
            if (wpdiscuzHelper::isPostedToday($comment)) {
                $posted_date = $this->optionsSerialized->phrases['wc_posted_today_text'] . ' ' . mysql2date($timeFormat, $comment->comment_date);
            } else {
                $posted_date = get_comment_date($dateFormat . ' ' . $timeFormat, $comment->comment_ID);
            }
        } else {
            $posted_date = $this->helper->dateDiff(time(), strtotime($comment->comment_date_gmt), 2);
        }

        $replyText = $this->optionsSerialized->phrases['wc_reply_text'];
        $shareText = $this->optionsSerialized->phrases['wc_share_text'];
        if (isset($rootComment) && $rootComment->comment_approved != 1) {
            $commentWrapperClass .= 'wc-comment';
        } else {
            $commentWrapperClass .= ($comment->comment_parent && $this->optionsSerialized->wordpressThreadComments) ? 'wc-comment wc-reply' : 'wc-comment';
        }
        $voteCount = isset($comment->meta_value) ? $comment->meta_value : get_comment_meta($comment->comment_ID, WpdiscuzCore::META_KEY_VOTES, true);

        $authorName = $comment->comment_author ? $comment->comment_author : __('Anonymous', 'wpdiscuz');
        $authorName = apply_filters('wpdiscuz_comment_author', $authorName, $comment);
        $profileUrl = apply_filters('wpdiscuz_profile_url', $profileUrl, $user);
        $authorAvatarField = apply_filters('wpdiscuz_author_avatar_field', $authorAvatarField, $comment, $user, $profileUrl);        
        $authorAvatar = $this->optionsSerialized->wordpressShowAvatars ? get_avatar($authorAvatarField) : '';

        if ($profileUrl) {
            $commentAuthorAvatar = "<a href='$profileUrl'>" . $authorAvatar . "</a>";
        } else {
            $commentAuthorAvatar = $authorAvatar;
        }

        if ($commentAuthorUrl) {
            $authorName = "<a rel='nofollow' href='$commentAuthorUrl'>" . $authorName . "</a>";
        } else if ($profileUrl) {
            $authorName = "<a rel='nofollow' href='$profileUrl'>" . $authorName . "</a>";
        }

        if (!$this->optionsSerialized->isGuestCanVote && !$current_user->ID) {
            $voteClass = ' wc_tooltipster';
            $voteTitleText = $this->optionsSerialized->phrases['wc_login_to_vote'];
            $voteUp = $voteTitleText;
            $voteDown = $voteTitleText;
        } else {
            $voteClass = ' wc_vote wc_tooltipster';
            $voteUp = $this->optionsSerialized->phrases['wc_vote_up'];
            $voteDown = $this->optionsSerialized->phrases['wc_vote_down'];
        }

        $commentContentClass = '';
        // begin printing comment template
        $output .= '<div id="wc-comm-' . $uniqueId . '" class="' . $commentWrapperClass . ' ' . $authorClass . ' wc_comment_level-' . $depth . '">';
        if ($this->optionsSerialized->wordpressShowAvatars) {
            $output .= '<div class="wc-comment-left">' . $commentAuthorAvatar;
            if (!$this->optionsSerialized->authorTitlesShowHide) {
                $output .= '<div class="' . $authorClass . ' wc-comment-label">' . $author_title . '</div>';
            }
            $afterLabelHtml = apply_filters('wpdiscuz_after_label', $afterLabelHtml = '', $comment);
            $output .= $afterLabelHtml;
            $output .= '</div>';
        }

        $commentLink = get_comment_link($comment);
        $output .= '<div id="comment-' . $comment->comment_ID . '" class="wc-comment-right ' . $commentContentClass . '" ' . $hideAvatarStyle . '>';
        $output .= '<div class="wc-comment-header">';
        $output .= '<div class="wc-comment-author">' . $authorName . '</div>';
        if (!$this->optionsSerialized->showHideCommentLink) {
            $output .= '<div class="wc-comment-link"><img src="' . plugins_url(WPDISCUZ_DIR_NAME . '/assets/img/icon-link.gif') . '" class="wc-comment-img-link" title="&lt;input type=&quot;text&quot; class=&quot;wc-comment-link-input&quot; value=&quot;' . $commentLink . '&quot; /&gt;" /></div>';
        }
        $output .= '<div class="wc-comment-date">' . $posted_date . '</div><div style="clear:right"></div>';
        $output .= '</div>';
        $output .= '<div class="wc-comment-text">' . $commentContent . '</div>';
        if ($comment->comment_approved == '1') {
            $output .= '<div class="wc-comment-footer">';
            if (!$this->optionsSerialized->votingButtonsShowHide) {
                $output .= '<div class="wc-vote-result">' . $voteCount . '</div>';
                $output .= ' <span  class="wc-vote-link wc-up ' . $voteClass . '" title="' . $voteUp . '"><img src="' . plugins_url(WPDISCUZ_DIR_NAME . '/assets/img/thumbs-up.png') . '"  align="absmiddle" class="wc-vote-img-up" /></span> &nbsp;|&nbsp; <span class="wc-vote-link wc-down ' . $voteClass . '" title="' . $voteDown . '"><img src="' . plugins_url(WPDISCUZ_DIR_NAME . '/assets/img/thumbs-down.png') . '"  align="absmiddle" class="wc-vote-img-down" /></span>&nbsp;';
            }

            if (comments_open($comment->comment_post_ID) && $this->optionsSerialized->wordpressThreadComments) {
                if ($this->optionsSerialized->wordpressCommentRegistration) {
                    if (!$this->optionsSerialized->replyButtonMembersShowHide && $current_user->ID) {
                        $output .= '&nbsp;&nbsp;<span  class="wc-reply-link" title="' . $replyText . '">' . $replyText . '</span> &nbsp;&nbsp;';
                    } else if (in_array('administrator', $current_user->roles)) {
                        $output .= '&nbsp;&nbsp;<span  class="wc-reply-link" title="' . $replyText . '">' . $replyText . '</span> &nbsp;&nbsp;';
                    }
                } else {
                    if (!$this->optionsSerialized->replyButtonMembersShowHide && !$this->optionsSerialized->replyButtonGuestsShowHide) {
                        $output .= '&nbsp;&nbsp;<span class="wc-reply-link" title="' . $replyText . '">' . $replyText . '</span> &nbsp;&nbsp;';
                    } else if (!$this->optionsSerialized->replyButtonMembersShowHide && $current_user->ID) {
                        $output .= '&nbsp;&nbsp;<span class="wc-reply-link" title="' . $replyText . '">' . $replyText . '</span> &nbsp;&nbsp;';
                    } else if (!$this->optionsSerialized->replyButtonGuestsShowHide && !$current_user->ID) {
                        $output .= '&nbsp;&nbsp;<span class="wc-reply-link" title="' . $replyText . '">' . $replyText . '</span> &nbsp;&nbsp;';
                    } else if (in_array('administrator', $current_user->roles)) {
                        $output .= '&nbsp;&nbsp;<span class="wc-reply-link" title="' . $replyText . '">' . $replyText . '</span> &nbsp;&nbsp;';
                    }
                }
            }

            if ($this->optionsSerialized->shareButtons) {
                $output .= '-&nbsp;&nbsp; <span class="wc-share-link" title="' . $shareText . '">' . $shareText . '</span> &nbsp;&nbsp;';
                $twitt_content = strip_tags($commentContent) . ' ' . $commentLink;
                $output .= '<span class="share_buttons_box">';
                $output .= in_array('fb', $this->optionsSerialized->shareButtons) ? '<a class="wc_tooltipster" target="_blank" href="http://www.facebook.com/sharer.php" title="' . $this->optionsSerialized->phrases['wc_share_facebook'] . '"><img src="' . plugins_url(WPDISCUZ_DIR_NAME . '/assets/img/social-icons/fb-18x18.png') . '" onmouseover="this.src=\'' . plugins_url(WPDISCUZ_DIR_NAME . '/assets/img/social-icons/fb-18x18-orig.png') . '\'" onmouseout="this.src=\'' . plugins_url(WPDISCUZ_DIR_NAME . '/assets/img/social-icons/fb-18x18.png') . '\'"/></a>&nbsp;&nbsp;' : '';
                $output .= in_array('twitter', $this->optionsSerialized->shareButtons) ? '<a class="wc_tooltipster" target="_blank" href="https://twitter.com/home?status=' . $twitt_content . '" title="' . $this->optionsSerialized->phrases['wc_share_twitter'] . '"><img src="' . plugins_url(WPDISCUZ_DIR_NAME . '/assets/img/social-icons/twitter-18x18.png') . '" onmouseover="this.src=\'' . plugins_url(WPDISCUZ_DIR_NAME . '/assets/img/social-icons/twitter-18x18-orig.png') . '\'" onmouseout="this.src=\'' . plugins_url(WPDISCUZ_DIR_NAME . '/assets/img/social-icons/twitter-18x18.png') . '\'"/></a>&nbsp;&nbsp;' : '';
                $output .= in_array('google', $this->optionsSerialized->shareButtons) ? '<a class="wc_tooltipster" target="_blank" href="https://plus.google.com/share?url=' . get_permalink($comment->comment_post_ID) . '" title="' . $this->optionsSerialized->phrases['wc_share_google'] . '"><img src="' . plugins_url(WPDISCUZ_DIR_NAME . '/assets/img/social-icons/google-18x18.png') . '" onmouseover="this.src=\'' . plugins_url(WPDISCUZ_DIR_NAME . '/assets/img/social-icons/google-18x18-orig.png') . '\'" onmouseout="this.src=\'' . plugins_url(WPDISCUZ_DIR_NAME . '/assets/img/social-icons/google-18x18.png') . '\'"/></a>&nbsp;&nbsp;' : '';
                $output .= in_array('vk', $this->optionsSerialized->shareButtons) ? '<a class="wc_tooltipster" target="_blank" href="http://vk.com/share.php?url=' . get_permalink($comment->comment_post_ID) . '" title="' . $this->optionsSerialized->phrases['wc_share_vk'] . '"><img src="' . plugins_url(WPDISCUZ_DIR_NAME . '/assets/img/social-icons/vk-18x18.png') . '" onmouseover="this.src=\'' . plugins_url(WPDISCUZ_DIR_NAME . '/assets/img/social-icons/vk-18x18-orig.png') . '\'" onmouseout="this.src=\'' . plugins_url(WPDISCUZ_DIR_NAME . '/assets/img/social-icons/vk-18x18.png') . '\'"/></a>&nbsp;&nbsp;' : '';
                $output .= in_array('ok', $this->optionsSerialized->shareButtons) ? '<a class="wc_tooltipster" target="_blank" href="http://www.odnoklassniki.ru/dk?st.cmd=addShare&st.s=1&st._surl=' . get_permalink($comment->comment_post_ID) . '" title="' . $this->optionsSerialized->phrases['wc_share_ok'] . '"><img src="' . plugins_url(WPDISCUZ_DIR_NAME . '/assets/img/social-icons/ok-18x18.png') . '" onmouseover="this.src=\'' . plugins_url(WPDISCUZ_DIR_NAME . '/assets/img/social-icons/ok-18x18-orig.png') . '\'" onmouseout="this.src=\'' . plugins_url(WPDISCUZ_DIR_NAME . '/assets/img/social-icons/ok-18x18.png') . '\'"/></a>&nbsp;&nbsp;' : '';
                $output .= '</span>';
            }

            if (current_user_can('edit_comment', $comment->comment_ID)) {
                $output .= '<span class="wc_editable_comment">-&nbsp;&nbsp;' . $this->optionsSerialized->phrases['wc_edit_text'] . '</span>';
                $output .= '<span class="wc_cancel_edit">-&nbsp;&nbsp;' . $this->optionsSerialized->phrases['wc_comment_edit_cancel_button'] . '</span>';
                $output .= '<span class="wc_save_edited_comment" style="display:none;">&nbsp;&nbsp;-&nbsp;&nbsp;' . $this->optionsSerialized->phrases['wc_comment_edit_save_button'] . '</span>';
            } else {
                $isInRange = $this->helper->isContentInRange($commentContent);
                $isEditable = $this->optionsSerialized->commentEditableTime == 'unlimit' ? true && $isInRange : $this->helper->isCommentEditable($comment) && $isInRange;
                if ($current_user->ID && $current_user->ID == $comment->user_id && $isEditable) {
                    $output .= '<span class="wc_editable_comment">-&nbsp;&nbsp;' . $this->optionsSerialized->phrases['wc_edit_text'] . '</span>';
                    $output .= '<span class="wc_cancel_edit">-&nbsp;&nbsp;' . $this->optionsSerialized->phrases['wc_comment_edit_cancel_button'] . '</span>';
                    $output .= '<span class="wc_save_edited_comment" style="display:none;">&nbsp;&nbsp;-&nbsp;&nbsp;' . $this->optionsSerialized->phrases['wc_comment_edit_save_button'] . '</span>';
                }
            }

            if ($depth < $this->optionsSerialized->wordpressThreadCommentsDepth && $this->optionsSerialized->wordpressThreadComments) {
                $output .= '<span class="wc-toggle wpdiscuz-hidden">' . $this->optionsSerialized->phrases['wc_hide_replies_text'] . ' &and;' . '</span>';
            }
            $output .= '</div>';
        }
        $output .= '</div>';
        $output .= '<div class="wpdiscuz-comment-message"></div>';
        $output .= '<div id="wpdiscuz_form_anchor-' . $uniqueId . '"  style="clear:both"></div>';
    }

    public function end_el(&$output, $comment, $depth = 0, $args = array()) {
        $output .= '</div>';
        return $output;
    }

}
