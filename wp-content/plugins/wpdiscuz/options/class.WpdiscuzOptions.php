<?php

class WpdiscuzOptions {

    private $optionsSerialized;
    private $dbManager;
    private $postTypes;
    private $blogRoles;
    private $shareButtons;

    public function __construct($optionsSerialized, $dbManager) {
        $this->dbManager = $dbManager;
        $this->optionsSerialized = $optionsSerialized;
        $this->initShareButtons();
    }

    public function mainOptionsForm() {
        $defaultPostTypes = get_post_types('', 'names');
        foreach ($defaultPostTypes as $postType) {
            if ($postType != 'revision' && $postType != 'nav_menu_item') {
                $this->postTypes[] = $postType;
            }
        }

        if (isset($_POST['wc_submit_options'])) {

            if (function_exists('current_user_can') && !current_user_can('manage_options')) {
                die(_e('Hacker?', 'wpdiscuz'));
            }

            if (function_exists('check_admin_referer')) {
                check_admin_referer('wc_options_form');
            }

            $this->optionsSerialized->postTypes = isset($_POST['wc_post_types']) ? $_POST['wc_post_types'] : array();
            $this->optionsSerialized->commentListUpdateType = isset($_POST['wc_comment_list_update_type']) ? $_POST['wc_comment_list_update_type'] : 0;
            $this->optionsSerialized->commentListUpdateTimer = isset($_POST['wc_comment_list_update_timer']) ? $_POST['wc_comment_list_update_timer'] : 30;
            $this->optionsSerialized->liveUpdateGuests = isset($_POST['wc_live_update_guests']) ? $_POST['wc_live_update_guests'] : 0;
            $this->optionsSerialized->commentEditableTime = isset($_POST['wc_comment_editable_time']) ? $_POST['wc_comment_editable_time'] : 900;
            $this->optionsSerialized->redirectPage = isset($_POST['wpdiscuz_redirect_page']) ? $_POST['wpdiscuz_redirect_page'] : 0;
            $this->optionsSerialized->isGuestCanVote = isset($_POST['wc_is_guest_can_vote']) ? $_POST['wc_is_guest_can_vote'] : 0;
            $this->optionsSerialized->commentListLoadType = isset($_POST['commentListLoadType']) ? $_POST['commentListLoadType'] : 0;
            $this->optionsSerialized->votingButtonsShowHide = isset($_POST['wc_voting_buttons_show_hide']) ? $_POST['wc_voting_buttons_show_hide'] : 0;
            $this->optionsSerialized->shareButtons = isset($_POST['wpdiscuz_share_buttons']) ? $_POST['wpdiscuz_share_buttons'] : array();
            $this->optionsSerialized->captchaShowHide = isset($_POST['wc_captcha_show_hide']) ? $_POST['wc_captcha_show_hide'] : 0;
            $this->optionsSerialized->captchaShowHideForMembers = isset($_POST['wc_captcha_show_hide_for_members']) ? $_POST['wc_captcha_show_hide_for_members'] : 0;
            $this->optionsSerialized->weburlShowHide = isset($_POST['wc_weburl_show_hide']) ? $_POST['wc_weburl_show_hide'] : 0;
            $this->optionsSerialized->headerTextShowHide = isset($_POST['wc_header_text_show_hide']) ? $_POST['wc_header_text_show_hide'] : 0;
            $this->optionsSerialized->isNameFieldRequired = isset($_POST['wc_is_name_field_required']) ? $_POST['wc_is_name_field_required'] : 0;
            $this->optionsSerialized->isEmailFieldRequired = isset($_POST['wc_is_email_field_required']) ? $_POST['wc_is_email_field_required'] : 0;
            $this->optionsSerialized->storeCommenterData = isset($_POST['storeCommenterData']) && (intval($_POST['storeCommenterData']) || $_POST['storeCommenterData'] == 0)? $_POST['storeCommenterData'] : -1;
            $this->optionsSerialized->showHideLoggedInUsername = isset($_POST['wc_show_hide_loggedin_username']) ? $_POST['wc_show_hide_loggedin_username'] : 0;
            $this->optionsSerialized->replyButtonGuestsShowHide = isset($_POST['wc_reply_button_guests_show_hide']) ? $_POST['wc_reply_button_guests_show_hide'] : 0;
            $this->optionsSerialized->replyButtonMembersShowHide = isset($_POST['wc_reply_button_members_show_hide']) ? $_POST['wc_reply_button_members_show_hide'] : 0;
            $this->optionsSerialized->authorTitlesShowHide = isset($_POST['wc_author_titles_show_hide']) ? $_POST['wc_author_titles_show_hide'] : 0;
            $this->optionsSerialized->simpleCommentDate = isset($_POST['wc_simple_comment_date']) ? $_POST['wc_simple_comment_date'] : 0;
            $this->optionsSerialized->showSubscriptionBar = isset($_POST['show_subscription_bar']) ? $_POST['show_subscription_bar'] : 0;
            $this->optionsSerialized->showHideReplyCheckbox = isset($_POST['wc_show_hide_reply_checkbox']) ? $_POST['wc_show_hide_reply_checkbox'] : 0;
            $this->optionsSerialized->showSortingButtons = isset($_POST['show_sorting_buttons']) ? $_POST['show_sorting_buttons'] : 0;
            $this->optionsSerialized->mostVotedByDefault = isset($_POST['mostVotedByDefault']) ? $_POST['mostVotedByDefault'] : 0;
            $this->optionsSerialized->usePostmaticForCommentNotification = isset($_POST['wc_use_postmatic_for_comment_notification']) ? $_POST['wc_use_postmatic_for_comment_notification'] : 0;
            $this->optionsSerialized->formBGColor = isset($_POST['wc_form_bg_color']) ? $_POST['wc_form_bg_color'] : '#f9f9f9';
            $this->optionsSerialized->commentTextSize = isset($_POST['wc_comment_text_size']) ? $_POST['wc_comment_text_size'] : '14px';
            $this->optionsSerialized->commentBGColor = isset($_POST['wc_comment_bg_color']) ? $_POST['wc_comment_bg_color'] : '#fefefe';
            $this->optionsSerialized->replyBGColor = isset($_POST['wc_reply_bg_color']) ? $_POST['wc_reply_bg_color'] : '#f8f8f8';
            $this->optionsSerialized->commentTextColor = isset($_POST['wc_comment_text_color']) ? $_POST['wc_comment_text_color'] : '#555';
            $this->optionsSerialized->primaryColor = isset($_POST['wc_comment_username_color']) ? $_POST['wc_comment_username_color'] : '#00B38F';
            $this->optionsSerialized->blogRoles = isset($_POST['wc_blog_roles']) ? wp_parse_args($_POST['wc_blog_roles'], $this->optionsSerialized->blogRoles) : $this->optionsSerialized->blogRoles;
            $this->optionsSerialized->voteReplyColor = isset($_POST['wc_vote_reply_color']) ? $_POST['wc_vote_reply_color'] : '#666666';
            $this->optionsSerialized->inputBorderColor = isset($_POST['wc_input_border_color']) ? $_POST['wc_input_border_color'] : '#d9d9d9';
            $this->optionsSerialized->newLoadedCommentBGColor = isset($_POST['wc_new_loaded_comment_bg_color']) ? $_POST['wc_new_loaded_comment_bg_color'] : '#FFFAD6';
            $this->optionsSerialized->customCss = isset($_POST['wc_custom_css']) ? $_POST['wc_custom_css'] : '.comments-area{width:auto; margin: 0 auto;}';
            $this->optionsSerialized->showPluginPoweredByLink = isset($_POST['wc_show_plugin_powerid_by']) ? $_POST['wc_show_plugin_powerid_by'] : 0;
            $this->optionsSerialized->isUsePoMo = isset($_POST['wc_is_use_po_mo']) ? $_POST['wc_is_use_po_mo'] : 0;
            $this->optionsSerialized->disableMemberConfirm = isset($_POST['wc_disable_member_confirm']) ? $_POST['wc_disable_member_confirm'] : 0;
            $this->optionsSerialized->commentTextMinLength = (isset($_POST['wc_comment_text_min_length']) && intval($_POST['wc_comment_text_min_length']) > 0) ? intval($_POST['wc_comment_text_min_length']) : 1;
            $this->optionsSerialized->commentTextMaxLength = (isset($_POST['wc_comment_text_max_length']) && intval($_POST['wc_comment_text_max_length']) > 0) ? intval($_POST['wc_comment_text_max_length']) : '';
            $this->optionsSerialized->commentReadMoreLimit = (isset($_POST['commentWordsLimit']) && intval($_POST['commentWordsLimit']) >= 0) ? intval($_POST['commentWordsLimit']) : 100;
            $this->optionsSerialized->showHideCommentLink = isset($_POST['showHideCommentLink']) ? $_POST['showHideCommentLink'] : 0;
            $this->optionsSerialized->updateOptions();
        }
        include_once 'html-options.php';
    }

    public function phrasesOptionsForm() {

        if (isset($_POST['wc_submit_phrases'])) {
            if (function_exists('current_user_can') && !current_user_can('manage_options')) {
                die(_e('Hacker?', 'wpdiscuz'));
            }
            if (function_exists('check_admin_referer')) {
                check_admin_referer('wc_phrases_form');
            }
            $this->optionsSerialized->phrases['wc_leave_a_reply_text'] = $_POST['wc_leave_a_reply_text'];
            $this->optionsSerialized->phrases['wc_be_the_first_text'] = $_POST['wc_be_the_first_text'];
            $this->optionsSerialized->phrases['wc_header_text'] = $_POST['wc_header_text'];
            $this->optionsSerialized->phrases['wc_header_text_plural'] = $_POST['wc_header_text_plural'];
            $this->optionsSerialized->phrases['wc_header_on_text'] = $_POST['wc_header_on_text'];
            $this->optionsSerialized->phrases['wc_comment_start_text'] = $_POST['wc_comment_start_text'];
            $this->optionsSerialized->phrases['wc_comment_join_text'] = $_POST['wc_comment_join_text'];
            $this->optionsSerialized->phrases['wc_email_text'] = $_POST['wc_email_text'];
            $this->optionsSerialized->phrases['wc_name_text'] = $_POST['wc_name_text'];
            $this->optionsSerialized->phrases['wc_website_text'] = $_POST['wc_website_text'];
            $this->optionsSerialized->phrases['wc_captcha_text'] = $_POST['wc_captcha_text'];
            $this->optionsSerialized->phrases['wc_submit_text'] = $_POST['wc_submit_text'];
            $this->optionsSerialized->phrases['wc_notify_of'] = $_POST['wc_notify_of'];
            $this->optionsSerialized->phrases['wc_notify_on_new_comment'] = $_POST['wc_notify_on_new_comment'];
            $this->optionsSerialized->phrases['wc_notify_on_all_new_reply'] = $_POST['wc_notify_on_all_new_reply'];
            $this->optionsSerialized->phrases['wc_notify_on_new_reply'] = $_POST['wc_notify_on_new_reply'];
            $this->optionsSerialized->phrases['wc_sort_by'] = $_POST['wc_sort_by'];
            $this->optionsSerialized->phrases['wc_newest'] = $_POST['wc_newest'];
            $this->optionsSerialized->phrases['wc_oldest'] = $_POST['wc_oldest'];
            $this->optionsSerialized->phrases['wc_most_voted'] = $_POST['wc_most_voted'];
            $this->optionsSerialized->phrases['wc_load_more_submit_text'] = $_POST['wc_load_more_submit_text'];
            $this->optionsSerialized->phrases['wc_load_rest_comments_submit_text'] = $_POST['wc_load_rest_comments_submit_text'];
            $this->optionsSerialized->phrases['wc_reply_text'] = $_POST['wc_reply_text'];
            $this->optionsSerialized->phrases['wc_share_text'] = $_POST['wc_share_text'];
            $this->optionsSerialized->phrases['wc_edit_text'] = $_POST['wc_edit_text'];
            $this->optionsSerialized->phrases['wc_share_facebook'] = $_POST['wc_share_facebook'];
            $this->optionsSerialized->phrases['wc_share_twitter'] = $_POST['wc_share_twitter'];
            $this->optionsSerialized->phrases['wc_share_google'] = $_POST['wc_share_google'];
            $this->optionsSerialized->phrases['wc_share_vk'] = $_POST['wc_share_vk'];
            $this->optionsSerialized->phrases['wc_share_ok'] = $_POST['wc_share_ok'];
            $this->optionsSerialized->phrases['wc_hide_replies_text'] = $_POST['wc_hide_replies_text'];
            $this->optionsSerialized->phrases['wc_show_replies_text'] = $_POST['wc_show_replies_text'];
            $this->optionsSerialized->phrases['wc_email_subject'] = $_POST['wc_email_subject'];
            $this->optionsSerialized->phrases['wc_email_message'] = $_POST['wc_email_message'];
            $this->optionsSerialized->phrases['wc_new_reply_email_subject'] = $_POST['wc_new_reply_email_subject'];
            $this->optionsSerialized->phrases['wc_new_reply_email_message'] = $_POST['wc_new_reply_email_message'];
            $this->optionsSerialized->phrases['wc_subscribed_on_comment'] = $_POST['wc_subscribed_on_comment'];
            $this->optionsSerialized->phrases['wc_subscribed_on_all_comment'] = $_POST['wc_subscribed_on_all_comment'];
            $this->optionsSerialized->phrases['wc_subscribed_on_post'] = $_POST['wc_subscribed_on_post'];
            $this->optionsSerialized->phrases['wc_unsubscribe'] = $_POST['wc_unsubscribe'];
            $this->optionsSerialized->phrases['wc_ignore_subscription'] = $_POST['wc_ignore_subscription'];
            $this->optionsSerialized->phrases['wc_unsubscribe_message'] = $_POST['wc_unsubscribe_message'];
            $this->optionsSerialized->phrases['wc_subscribe_message'] = $_POST['wc_subscribe_message'];
            $this->optionsSerialized->phrases['wc_confirm_email'] = $_POST['wc_confirm_email'];
            $this->optionsSerialized->phrases['wc_comfirm_success_message'] = $_POST['wc_comfirm_success_message'];
            $this->optionsSerialized->phrases['wc_confirm_email_subject'] = $_POST['wc_confirm_email_subject'];
            $this->optionsSerialized->phrases['wc_confirm_email_message'] = $_POST['wc_confirm_email_message'];
            $this->optionsSerialized->phrases['wc_error_empty_text'] = $_POST['wc_error_empty_text'];
            $this->optionsSerialized->phrases['wc_error_email_text'] = $_POST['wc_error_email_text'];
            $this->optionsSerialized->phrases['wc_error_url_text'] = $_POST['wc_error_url_text'];
            $this->optionsSerialized->phrases['wc_year_text']['datetime'][0] = $_POST['wc_year_text'];
            $this->optionsSerialized->phrases['wc_year_text_plural']['datetime'][0] = $_POST['wc_year_text_plural'];
            $this->optionsSerialized->phrases['wc_month_text']['datetime'][0] = $_POST['wc_month_text'];
            $this->optionsSerialized->phrases['wc_month_text_plural']['datetime'][0] = $_POST['wc_month_text_plural'];
            $this->optionsSerialized->phrases['wc_day_text']['datetime'][0] = $_POST['wc_day_text'];
            $this->optionsSerialized->phrases['wc_day_text_plural']['datetime'][0] = $_POST['wc_day_text_plural'];
            $this->optionsSerialized->phrases['wc_hour_text']['datetime'][0] = $_POST['wc_hour_text'];
            $this->optionsSerialized->phrases['wc_hour_text_plural']['datetime'][0] = $_POST['wc_hour_text_plural'];
            $this->optionsSerialized->phrases['wc_minute_text']['datetime'][0] = $_POST['wc_minute_text'];
            $this->optionsSerialized->phrases['wc_minute_text_plural']['datetime'][0] = $_POST['wc_minute_text_plural'];
            $this->optionsSerialized->phrases['wc_second_text']['datetime'][0] = $_POST['wc_second_text'];
            $this->optionsSerialized->phrases['wc_second_text_plural']['datetime'][0] = $_POST['wc_second_text_plural'];
            $this->optionsSerialized->phrases['wc_right_now_text'] = $_POST['wc_right_now_text'];
            $this->optionsSerialized->phrases['wc_ago_text'] = $_POST['wc_ago_text'];
            $this->optionsSerialized->phrases['wc_posted_today_text'] = $_POST['wc_posted_today_text'];
            $this->optionsSerialized->phrases['wc_you_must_be_text'] = $_POST['wc_you_must_be_text'];
            $this->optionsSerialized->phrases['wc_logged_in_as'] = $_POST['wc_logged_in_as'];
            $this->optionsSerialized->phrases['wc_log_out'] = $_POST['wc_log_out'];
            $this->optionsSerialized->phrases['wc_logged_in_text'] = $_POST['wc_logged_in_text'];
            $this->optionsSerialized->phrases['wc_to_post_comment_text'] = $_POST['wc_to_post_comment_text'];
            $this->optionsSerialized->phrases['wc_vote_counted'] = $_POST['wc_vote_counted'];
            $this->optionsSerialized->phrases['wc_vote_up'] = $_POST['wc_vote_up'];
            $this->optionsSerialized->phrases['wc_vote_down'] = $_POST['wc_vote_down'];
            $this->optionsSerialized->phrases['wc_held_for_moderate'] = $_POST['wc_held_for_moderate'];
            $this->optionsSerialized->phrases['wc_vote_only_one_time'] = $_POST['wc_vote_only_one_time'];
            $this->optionsSerialized->phrases['wc_voting_error'] = $_POST['wc_voting_error'];
            $this->optionsSerialized->phrases['wc_self_vote'] = $_POST['wc_self_vote'];
            $this->optionsSerialized->phrases['wc_deny_voting_from_same_ip'] = $_POST['wc_deny_voting_from_same_ip'];
            $this->optionsSerialized->phrases['wc_login_to_vote'] = $_POST['wc_login_to_vote'];
            $this->optionsSerialized->phrases['wc_invalid_captcha'] = $_POST['wc_invalid_captcha'];
            $this->optionsSerialized->phrases['wc_invalid_field'] = $_POST['wc_invalid_field'];
            $this->optionsSerialized->phrases['wc_new_comment_button_text'] = $_POST['wc_new_comment_button_text'];
            $this->optionsSerialized->phrases['wc_new_comments_button_text'] = $_POST['wc_new_comments_button_text'];
            $this->optionsSerialized->phrases['wc_new_reply_button_text'] = $_POST['wc_new_reply_button_text'];
            $this->optionsSerialized->phrases['wc_new_replies_button_text'] = $_POST['wc_new_replies_button_text'];
            $this->optionsSerialized->phrases['wc_new_comments_text'] = $_POST['wc_new_comments_text'];
            $this->optionsSerialized->phrases['wc_comment_not_updated'] = $_POST['wc_comment_not_updated'];
            $this->optionsSerialized->phrases['wc_comment_edit_not_possible'] = $_POST['wc_comment_edit_not_possible'];
            $this->optionsSerialized->phrases['wc_comment_not_edited'] = $_POST['wc_comment_not_edited'];
            $this->optionsSerialized->phrases['wc_comment_edit_save_button'] = $_POST['wc_comment_edit_save_button'];
            $this->optionsSerialized->phrases['wc_comment_edit_cancel_button'] = $_POST['wc_comment_edit_cancel_button'];
            $this->optionsSerialized->phrases['wc_msg_comment_text_min_length'] = $_POST['wc_msg_comment_text_min_length'];
            $this->optionsSerialized->phrases['wc_msg_comment_text_max_length'] = $_POST['wc_msg_comment_text_max_length'];
            $this->optionsSerialized->phrases['wc_read_more'] = $_POST['wc_read_more'];
            $this->optionsSerialized->phrases['wc_msg_required_fields'] = $_POST['wc_msg_required_fields'];
            $this->optionsSerialized->phrases['wc_connect_with'] = $_POST['wc_connect_with'];
            $this->optionsSerialized->phrases['wc_subscribed_to'] = $_POST['wc_subscribed_to'];
            if (class_exists('Prompt_Comment_Form_Handling') && $this->optionsSerialized->usePostmaticForCommentNotification) {
                $this->optionsSerialized->phrases['wc_postmatic_subscription_label'] = $_POST['wc_postmatic_subscription_label'];
            }
            foreach ($this->optionsSerialized->blogRoles as $roleName => $roleVal) {
                $this->optionsSerialized->phrases['wc_blog_role_' . $roleName] = $_POST['wc_blog_role_' . $roleName];
            }
            $this->dbManager->updatePhrases($this->optionsSerialized->phrases);
        }
        $this->optionsSerialized->initPhrasesOnLoad();

        include_once 'html-phrases.php';
    }

    private function initShareButtons() {
        $this->shareButtons[] = 'fb';
        $this->shareButtons[] = 'twitter';
        $this->shareButtons[] = 'google';
        $this->shareButtons[] = 'vk';
        $this->shareButtons[] = 'ok';
    }

}
