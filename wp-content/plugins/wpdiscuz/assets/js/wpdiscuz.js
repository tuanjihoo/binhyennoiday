jQuery(document).ready(function ($) {
    $('body').addClass('wpdiscuz_313');
    wpdiscuzValidator.message['invalid'] = wpdiscuz_ajax_obj.wpdiscuz_options.wc_invalid_field;
    wpdiscuzValidator.message['empty'] = wpdiscuz_ajax_obj.wpdiscuz_options.wc_error_empty_text;
    wpdiscuzValidator.message['email'] = wpdiscuz_ajax_obj.wpdiscuz_options.wc_error_email_text;
    wpdiscuzValidator.message['url'] = wpdiscuz_ajax_obj.wpdiscuz_options.wc_error_url_text;
    wpdiscuzValidator.message['min'] = wpdiscuz_ajax_obj.wpdiscuz_options.wc_msg_comment_text_min_length;
    wpdiscuzValidator.message['max'] = wpdiscuz_ajax_obj.wpdiscuz_options.wc_msg_comment_text_max_length;

    var isUserLoggedIn = wpdiscuz_ajax_obj.wpdiscuz_options.is_user_logged_in;
    var isShowCaptchaForGuests = wpdiscuz_ajax_obj.wpdiscuz_options.wc_captcha_show_hide == 0 && !isUserLoggedIn;
    var isShowCaptchaForMembers = wpdiscuz_ajax_obj.wpdiscuz_options.wc_captcha_show_hide_for_members > 0 && isUserLoggedIn;
    var commentListLoadType = wpdiscuz_ajax_obj.wpdiscuz_options.commentListLoadType;
    var wordpressIsPaginate = wpdiscuz_ajax_obj.wpdiscuz_options.wordpressIsPaginate;
    var wpdiscuzPostId = wpdiscuz_ajax_obj.wpdiscuz_options.wc_post_id;
    var commentListUpdateType = wpdiscuz_ajax_obj.wpdiscuz_options.commentListUpdateType;
    var commentListUpdateTimer = wpdiscuz_ajax_obj.wpdiscuz_options.commentListUpdateTimer;
    var disableGuestsLiveUpdate = wpdiscuz_ajax_obj.wpdiscuz_options.liveUpdateGuests;
    var loadLastCommentId = wpdiscuz_ajax_obj.wpdiscuz_options.loadLastCommentId;
    var wpdiscuzCommentOrder = wpdiscuz_ajax_obj.wpdiscuz_options.wordpress_comment_order;
    var commentsVoteOrder = wpdiscuz_ajax_obj.wpdiscuz_options.commentsVoteOrder;
    var storeCommenterData = wpdiscuz_ajax_obj.wpdiscuz_options.storeCommenterData;
    var wpdiscuzLoadCount = 1;
    var wpdiscuzCommentOrderBy = 'comment_date_gmt';
    var wpdiscuzReplyArray = [];
    var wpdiscuzCommentArray = [];

    addTooltipster();
    loginButtonsClone();
    displayShowHideReplies();
    if (commentsVoteOrder) {
        $('.wpdiscuz-vote-sort-up').addClass('wpdiscuz-sort-button-active');
        wpdiscuzCommentOrderBy = 'by_vote';
    } else {
        $('.wpdiscuz-date-sort-' + wpdiscuzCommentOrder).addClass('wpdiscuz-sort-button-active');
    }
    $('#wc_unsubscribe_message').delay(7000).fadeOut(1500, function () {
        $(this).remove();
        location.href = location.href.substring(0, location.href.indexOf('subscribeAnchor') - 1);
    });

    if ($('.wc_main_comm_form').length) {
        setCookieInForm();
    }
    $(document).delegate('.wc-reply-link', 'click', function () {
        if ($(this).hasClass('wpdiscuz-clonned')) {
            $('#wc-secondary-form-wrapper-' + getUniqueID($(this), 0)).slideToggle(700);
        } else {
            cloneSecondaryForm($(this));
        }
        setCookieInForm();
    });

    $(document).delegate('textarea.wc_comment', 'focus', function () {
        if (!($(this).next('.autogrow-textarea-mirror').length)) {
            $(this).autoGrow();
        }
        var parent = $(this).parents('.wc-form-wrapper');
        $('.wc-form-footer', parent).slideDown(700);
    });

    $(document).delegate('.wc-share-link', 'click', function () {
        var parent = $(this).parents('.wc-comment-right');
        $('.share_buttons_box', parent).slideToggle(1000);
    });

    $(document).delegate('.wpdiscuz-nofollow,.wc_captcha_refresh_img,.wc-toggle,.wc-load-more-link', 'click', function (e) {
        e.preventDefault();
    });

    $(document).delegate('.wc-toggle', 'click', function () {
        var uniqueID = getUniqueID($(this), 0);
        var toggleSpan = $(this);
        $('#wc-comm-' + uniqueID + '> .wc-reply').slideToggle(700, function () {
            if ($(this).is(':hidden')) {
                toggleSpan.html(wpdiscuz_ajax_obj.wpdiscuz_options.wc_show_replies_text + ' &or;');
            } else {
                toggleSpan.html(wpdiscuz_ajax_obj.wpdiscuz_options.wc_hide_replies_text + ' &and;');
            }
        });
    });

    $(document).delegate('.wc-new-loaded-comment', 'mouseenter', function () {
        if ($(this).hasClass('wc-reply')) {
            $('>.wc-comment-right', this).animate({
                backgroundColor: wpdiscuz_ajax_obj.wpdiscuz_options.wc_reply_bg_color
            }, 1500);
        } else {
            $('>.wc-comment-right', this).animate({
                backgroundColor: wpdiscuz_ajax_obj.wpdiscuz_options.wc_comment_bg_color
            }, 1500);
        }
    });
    //============================== CAPTCHA ============================== //
    $(document).delegate('.wc_captcha_refresh_img', 'click', function () {
        changeCaptchaImage($(this));
    });
    function changeCaptchaImage(reloadImage) {
        if (isShowCaptchaForGuests || isShowCaptchaForMembers) {
            var form = reloadImage.parents('.wc-form-wrapper');
            var isMain = form.hasClass('wc-secondary-form-wrapper') ? 0 : 1;
            var uniqueId = getUniqueID(reloadImage, isMain);
            var data = 'uniqueId=' + uniqueId;
            var ajaxObject = getAjaxObject('generateCaptcha', data);
            ajaxObject.done(function (response) {
                try {
                    var obj = $.parseJSON(response);
                    if (obj.code == 1) {
                        var captchaImg = $(reloadImage).prev().children('.wc_captcha_img');
                        var src = captchaImg.attr('src');
                        var lastSlashIndex = src.lastIndexOf('/') + 1;
                        var newSrc = src.substring(0, lastSlashIndex) + obj.message;
                        captchaImg.attr('src', newSrc);
                        var keyField = $('.wpdiscuz-cnonce', form);
                        keyField.attr('id', obj.key);
                        keyField.attr('value', obj.key);
                    }
                } catch (e) {
                    console.log(e);
                }
                $('.wpdiscuz-loading-bar').hide();
            });
        }
    }
//============================== CAPTCHA ============================== //
//============================== ADD COMMENT FUNCTION ============================== // 

    $(document).delegate('.wc_comm_submit', 'click', function () {
        var depth = 1;
        var wcForm = $(this).parents('form');
        if (!wcForm.hasClass('wc_main_comm_form')) {
            depth = getCommentDepth($(this).parents('.wc-comment'));
        }
        if (!wpdiscuz_ajax_obj.wpdiscuz_options.is_email_field_required && $('.wc_email', wcForm).val()) {
            $('.wc_email', wcForm).attr('required', 'required');
        }

        if (!wpdiscuz_ajax_obj.wpdiscuz_options.is_email_field_required && !($('.wc_email', wcForm).val())) {
            $('.wc_email', wcForm).removeAttr('required');
            $('.wc_email', wcForm).next('.alert').html('');
        }

        if (wpdiscuzValidator.checkAll(wcForm)) {
            var data = wcForm.serialize() + '&wc_comment_depth=' + depth;
            if (isShowCaptchaForGuests || isShowCaptchaForMembers) {
                var image = $('.wc_captcha_img', wcForm);
                var src = image.attr('src');
                var lastIndex = src.lastIndexOf('/') + 1;
                var fileName = src.substring(lastIndex);
                data += '&fileName=' + fileName;
            }
            if (wpdiscuz_ajax_obj.wpdiscuz_options.wpdiscuz_zs) {
                data += '&wpdiscuz_zs=' + wpdiscuz_ajax_obj.wpdiscuz_options.wpdiscuz_zs;
            }
            getAjaxObject('addComment', data).done(function (response) {
                var messageKey = '';
                var message = '';
                try {
                    var obj = $.parseJSON(response);
                    messageKey = obj.code;
                    if (parseInt(messageKey) >= 0) {
                        var isMain = obj.is_main;
                        message = obj.message;
                        $('.wc_header_text_count').html(obj.wc_all_comments_count_new);
                        if (isMain) {
                            $('.wc-thread-wrapper').prepend(message);
                        } else {
                            $('#wc-secondary-form-wrapper-' + messageKey).slideToggle(700);
                            if (obj.is_in_same_container == 1) {
                                $('#wc-secondary-form-wrapper-' + messageKey).after(message);
                            } else {
                                $('#wc-secondary-form-wrapper-' + messageKey).after(message.replace('wc-reply', 'wc-reply wc-no-left-margin'));
                            }
                        }
                        notifySubscribers(obj);
                        wpdiscuzRedirect(obj);
                        addCookie(wcForm, obj);
                        wcForm.get(0).reset();
                        setCookieInForm();
                        displayShowHideReplies();
                        $('.wc_comment', wcForm).css('height', '45.6px');
                    } else {
                        message = wpdiscuz_ajax_obj.wpdiscuz_options[messageKey];
                        wpdiscuzSetCommentMsg(wcForm, messageKey, message, true);
                    }
                } catch (e) {
                    if (response.indexOf('<') >= 0 && response.indexOf('>') >= 0) {
                        message = e;
                    } else {
                        message = response;
                    }
                    wpdiscuzSetCommentMsg(wcForm, 'wc_invalid_field', message, true);
                }
                addTooltipster();
                $('.wpdiscuz-loading-bar').hide();
            });
        }
        changeCaptchaImage($('.wc_captcha_refresh_img', wcForm));
    });

    function notifySubscribers(obj) {
        if (!obj.held_moderate) {
            var data = 'comment_id=' + obj.new_comment_id + '&email=' + obj.user_email + '&isParent=' + obj.is_main;
            var ajaxObject = getAjaxObject('checkNotificationType', data);
            ajaxObject.done(function (response) {
                try {
                    obj = $.parseJSON(response);
                } catch (e) {
                    console.log(e);
                }
            });
        }
    }

    function wpdiscuzRedirect(obj) {
        if (obj.redirect > 0 && obj.new_comment_id) {
            var data = 'commentId=' + obj.new_comment_id;
            var ajaxObject = getAjaxObject('redirect', data);
            ajaxObject.done(function (response) {
                obj = $.parseJSON(response);
                if (obj.code == 1) {
                    setTimeout(function () {
                        window.location.href = obj.redirect_to;
                    }, 5000);
                }
            });
        }
    }

    function setCookieInForm() {
        if ($.cookie('wc_author_name')) {
            $('.wc_comm_form .wc_name').val($.cookie('wc_author_name'));
        }
        if ($.cookie('wc_author_email')) {
            $('.wc_comm_form .wc_email').val($.cookie('wc_author_email'));
        }
        if ($.cookie('wc_author_website')) {
            $('.wc_comm_form .wc_website').val($.cookie('wc_author_website'));
        }
    }

    function addCookie(wcForm, obj) {
        var email = '';
        var name = '';
        if ($('.wc_email', wcForm).val()) {
            email = $('.wc_email', wcForm).val();
        } else {
            email = obj.user_email;
        }
        if ($('.wc_name', wcForm).val()) {
            name = $('.wc_name', wcForm).val();
        } else {
            name = obj.user_name;
        }
        console.log(storeCommenterData);
        if (storeCommenterData == null) {
            $.cookie('wc_author_email', email);
            $.cookie('wc_author_name', name);
            $.cookie('wc_author_website', $('.wc_website', wcForm).val());
        } else {
            storeCommenterData = parseInt(storeCommenterData);
            $.cookie('wc_author_email', email, {expires: storeCommenterData, path: '/'});
            $.cookie('wc_author_name', name, {expires: storeCommenterData, path: '/'});
            $.cookie('wc_author_website', $('.wc_website', wcForm).val(), {expires: storeCommenterData, path: '/'});
        }
    }
//============================== ADD COMMENT FUNCTION ============================== // 
//============================== EDIT COMMENT FUNCTION ============================== // 
    var wcCommentTextBeforeEditing;

    $(document).delegate('.wc_editable_comment', 'click', function () {
        var uniqueID = getUniqueID($(this), 0);
        var commentID = getCommentID(uniqueID);
        var editButton = $(this);
        var data = 'commentId=' + commentID;
        wcCommentTextBeforeEditing = $('#wc-comm-' + uniqueID + ' .wc-comment-text').html();
        getAjaxObject('editComment', data).done(function (response) {
            try {
                var obj = $.parseJSON(response);
                var message = '';
                var messageKey = obj.code;
                if (parseInt(messageKey) >= 0) {
                    var editableTextarea = '<textarea required="required" name="wc_comment" class="wc_comment wc_field_input wc_edit_comment" id="wc_edit_comment-' + uniqueID + '" style="min-height: 2em;">' + obj.message + '</textarea>';
                    $('#wc-comm-' + uniqueID + ' > .wc-comment-right .wc-comment-text').replaceWith(editableTextarea);
                    document.getElementById('wc_edit_comment-' + uniqueID).focus();
                    $('#wc-comm-' + uniqueID + ' > .wc-comment-right .wc-comment-footer .wc_save_edited_comment').show();
                    editableTextarea = '';
                    $('#wc-comm-' + uniqueID + ' > .wc-comment-right .wc-comment-footer .wc_editable_comment').hide();
                    $('#wc-comm-' + uniqueID + ' > .wc-comment-right .wc-comment-footer .wc_cancel_edit').show();
                } else {
                    message = wpdiscuz_ajax_obj.wpdiscuz_options[messageKey];
                    wpdiscuzSetCommentMsg(editButton, messageKey, message, false);
                }
            } catch (e) {
                console.log(e);
            }
            $('.wpdiscuz-loading-bar').hide();
        });
    });

    $(document).delegate('.wc_save_edited_comment', 'click', function () {
        var uniqueID = getUniqueID($(this));
        var commentID = getCommentID(uniqueID);
        var editableTextarea = $('#wc-comm-' + uniqueID + ' textarea#wc_edit_comment-' + uniqueID);
        var commentContent = editableTextarea.val();
        var saveButton = $(this);
        if ($.trim(commentContent).length > 0) {
            var data = 'commentId=' + commentID + '&commentContent=' + commentContent;
            getAjaxObject('saveEditedComment', data).done(function (response) {
                try {
                    var obj = $.parseJSON(response);
                    var messageKey = obj.code;
                    var message = '';
                    if (parseInt(messageKey) >= 0) {
                        wcCancelOrSave(uniqueID, obj.message);
                    } else {
                        message = wpdiscuz_ajax_obj.wpdiscuz_options[messageKey];
                        wpdiscuzSetCommentMsg(saveButton, messageKey, message, false);
                    }
                    editableTextarea = '';
                    commentContent = '';
                } catch (e) {
                    console.log(e);
                }
                $('.wpdiscuz-loading-bar').hide();
            });
        }
    });

    $(document).delegate('.wc_cancel_edit', 'click', function () {
        var uniqueID = getUniqueID($(this));
        wcCancelOrSave(uniqueID, wcCommentTextBeforeEditing);
    });

    function wcCancelOrSave(uniqueID, content) {
        $('#wc-comm-' + uniqueID + ' > .wc-comment-right .wc-comment-footer .wc_editable_comment').show();
        $('#wc-comm-' + uniqueID + ' > .wc-comment-right .wc-comment-footer .wc_cancel_edit').hide();
        $('#wc-comm-' + uniqueID + ' > .wc-comment-right .wc-comment-footer .wc_save_edited_comment').hide();
        var commentContentWrapper = '<div class="wc-comment-text">' + nl2br(content) + '</div>';
        $('#wc-comm-' + uniqueID + ' #wc_edit_comment-' + uniqueID).replaceWith(commentContentWrapper);
    }

    function nl2br(str, is_xhtml) {
        var breakTag = (is_xhtml || typeof is_xhtml === 'undefined') ? '<br/>' : '<br>';
        var string = (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + breakTag + '$2');
        return string.replace('<br><br>', '<br/>');
    }
//============================== EDIT COMMENT FUNCTION ============================== // 
//============================== LOAD MORE ============================== // 
    $(document).delegate('.wc-load-more-submit', 'click', function () {
        wpdiscuzLoadComments();
    });

    var wpdiscuzHasMoreComments = $('#wpdiscuzHasMoreComments').val();
    var isRun = false;
    if (commentListLoadType == 2 && !wordpressIsPaginate) {
        $('.wc-load-more-submit').parents('.wpdiscuz-comment-pagination').hide();
        $(window).scroll(function () {
            var scrollHeight = document.getElementById('wcThreadWrapper').scrollHeight;
            if ($(window).scrollTop() >= scrollHeight && isRun === false && wpdiscuzHasMoreComments == 1) {
                isRun = true;
                wpdiscuzLoadComments();
            }
        });
    }

    function wpdiscuzLoadComments() {
        var data;
        data = 'offset=' + wpdiscuzLoadCount + '&orderBy=' + wpdiscuzCommentOrderBy + '&order=' + wpdiscuzCommentOrder + '&lastParentId=' + getLastParentID();
        wpdiscuzLoadCount++;
        getAjaxObject('loadMoreComments', data).done(function (response) {
            try {
                var obj = $.parseJSON(response);
                $('.wpdiscuz-comment-pagination').before(obj.comment_list);
                setLoadMoreVisibility(obj);
                $('.wpdiscuz_single').remove();
                isRun = false;
                displayShowHideReplies();
            } catch (e) {
                console.log(e);
            }
            addTooltipster();
            $('.wpdiscuz-loading-bar').hide();
            $('.wc-load-more-submit').blur();
        });
    }

    function setLoadMoreVisibility(obj) {
        var hasMoreComments = 0;
        if (obj.is_show_load_more == false) {
            hasMoreComments = 0;
            wpdiscuzHasMoreComments = 0;
            $('.wc-load-more-submit').parents('.wpdiscuz-comment-pagination').hide();
        } else {
            setLastParentID(obj.last_parent_id);
            wpdiscuzHasMoreComments = 1;
            hasMoreComments = 1;
        }
        $('#wpdiscuzHasMoreComments').val(hasMoreComments);
    }

//============================== LOAD MORE ============================== // 
//============================== VOTE  ============================== // 
    $(document).delegate('.wc_vote', 'click', function () {
        var currentVoteBtn = $(this);
        var messageKey = '';
        var message = '';
        var commentID = $(this).parents('.wc-comment-right').attr('id');
        commentID = commentID.substring(commentID.lastIndexOf('-') + 1);
        var voteType;
        if ($(this).hasClass('wc-up')) {
            voteType = 1;
        } else {
            voteType = -1;
        }
        var data = 'commentId=' + commentID + '&voteType=' + voteType;

        getAjaxObject('voteOnComment', data).done(function (response) {
            try {
                var obj = $.parseJSON(response);
                messageKey = obj.code;
                if (parseInt(messageKey) >= 0) {
                    var voteCountDiv = $('.wc-comment-footer .wc-vote-result', $('#comment-' + commentID));
                    $(voteCountDiv).text(parseInt($(voteCountDiv).text()) + voteType);
                } else {
                    message = wpdiscuz_ajax_obj.wpdiscuz_options[messageKey];
                    wpdiscuzSetCommentMsg(currentVoteBtn, messageKey, message, false);
                }
            } catch (e) {
                console.log(e);
            }
            $('.wpdiscuz-loading-bar').hide();
        });
    });
//============================== VOTE ============================== //
//============================== SORTING ============================== //
    $(document).delegate('.wpdiscuz-sort-button', 'click', function () {
        wpdiscuzHasMoreComments = $('#wpdiscuzHasMoreComments').val();
        if (!($(this).hasClass('wpdiscuz-sort-button-active'))) {
            var clickedBtn = $(this);
            if ($(this).hasClass('wpdiscuz-vote-sort-up')) {
                wpdiscuzCommentOrderBy = 'by_vote';
                wpdiscuzCommentOrder = 'desc';
            } else {
                wpdiscuzCommentOrderBy = 'comment_date_gmt';
                wpdiscuzCommentOrder = $(this).hasClass('wpdiscuz-date-sort-desc') ? 'desc' : 'asc';
            }
            var data = 'orderBy=' + wpdiscuzCommentOrderBy + '&order=' + wpdiscuzCommentOrder;
            var messageKey = '';
            var message = '';
            getAjaxObject('wpdiscuzSorting', data).done(function (response) {
                try {
                    var obj = $.parseJSON(response);
                    messageKey = obj.code;
                    message = obj.message;
                    if (parseInt(messageKey) > 0) {
                        $('#wpcomm .wc-thread-wrapper .wc-comment').each(function () {
                            $(this).remove();
                        });
                        $('#wpcomm .wc-thread-wrapper').prepend(message);
                        wpdiscuzLoadCount = parseInt(obj.loadCount);
                    } else {
                    }
                    setActiveButton(clickedBtn);
                    setLoadMoreVisibility(obj);
                } catch (e) {
                    console.log(e);
                }
                displayShowHideReplies();
                addTooltipster();
                $('.wpdiscuz-loading-bar').hide();
            });
        }
    });

    function setActiveButton(clickedBtn) {
        $('.wpdiscuz-sort-buttons .wpdiscuz-sort-button').each(function () {
            $(this).removeClass('wpdiscuz-sort-button-active');
        });
        clickedBtn.addClass('wpdiscuz-sort-button-active');
    }

//============================== SORTING ============================== // 
//============================== SINGLE COMMENT ============================== // 
    function getSingleComment() {
        var loc = location.href;
        var matches = loc.match(/#comment\-(\d+)/);
        if (matches !== null) {
            var commentId = matches[1];
            if (!$('#comment-' + commentId).length) {
                var data = 'commentId=' + commentId;
                var ajaxObject = getAjaxObject('getSingleComment', data);
                ajaxObject.done(function (response) {
                    try {
                        var obj = $.parseJSON(response);
                        $('.wc-thread-wrapper').prepend(obj.message);
                        $('html, body').animate({
                            scrollTop: $(".wc-thread-wrapper").offset().top
                        }, 1000);
                    } catch (e) {
                        console.log(e);
                    }
                    addTooltipster();
                    $('.wpdiscuz-loading-bar').hide();
                });
            }
        }
    }
    getSingleComment();
//============================== SINGLE COMMENT ============================== //
//============================== LIVE UPDATE ============================== // 
    if (commentListUpdateType > 0 && loadLastCommentId && (isUserLoggedIn || (!isUserLoggedIn && !disableGuestsLiveUpdate))) {
        setInterval(liveUpdate, parseInt(commentListUpdateTimer) * 1000);
    }

    function liveUpdate() {
        var visibleCommentIds = getVisibleCommentIds();
        var email = ($.cookie('wc_author_email') != undefined && $.cookie('wc_author_email') != '') ? $.cookie('wc_author_email') : '';
        var data = 'loadLastCommentId=' + loadLastCommentId + "&visibleCommentIds=" + visibleCommentIds + '&email=' + email;
        var ajaxObject = getAjaxObject('liveUpdate', data);
        ajaxObject.done(function (response) {
            try {
                var obj = $.parseJSON(response);
                if (obj.code == 1) {
                    if (commentListUpdateType == 1) {
                        liveUpdateImmediately(obj);
                    } else {
                        wpdiscuzCommentArray = wpdiscuzCommentArray.concat(obj.message.comments);
                        wpdiscuzReplyArray = wpdiscuzReplyArray.concat(obj.message.author_replies);
                        var newCommentArrayLength = wpdiscuzCommentArray.length;
                        var newRepliesArrayLength = wpdiscuzReplyArray.length;
                        if (newCommentArrayLength > 0) {
                            var newCommentText = newCommentArrayLength + ' ';
                            newCommentText += newCommentArrayLength > 1 ? wpdiscuz_ajax_obj.wpdiscuz_options.wc_new_comments_button_text : wpdiscuz_ajax_obj.wpdiscuz_options.wc_new_comment_button_text;
                            $('.wc_new_comment').html(newCommentText).show();
                        } else {
                            $('.wc_new_comment').hide();
                        }
                        if (newRepliesArrayLength > 0) {
                            var newReplyText = newRepliesArrayLength + ' ';
                            newReplyText += newRepliesArrayLength > 1 ? wpdiscuz_ajax_obj.wpdiscuz_options.wc_new_replies_button_text : wpdiscuz_ajax_obj.wpdiscuz_options.wc_new_reply_button_text;
                            $('.wc_new_reply').html(newReplyText).show();
                        } else {
                            $('.wc_new_reply').hide();
                        }
                    }
                    $('.wc_header_text_count').html(obj.wc_all_comments_count_new);
                    loadLastCommentId = obj.loadLastCommentId;
                }
            } catch (e) {
                console.log(e);
            }
            addTooltipster();
            $('.wpdiscuz-loading-bar').hide();
        });
    }

    function liveUpdateImmediately(obj) {
        if (obj.message !== undefined) {
            var commentObject;
            var message = obj.message;
            for (var i = 0; i < message.length; i++) {
                commentObject = message[i];
                addCommentToTree(commentObject.comment_parent, commentObject.comment_html);
            }
            displayShowHideReplies();
        }
    }

    $(document).delegate('.wc-update-on-click', 'click', function () {
        var data;
        var clickedButton = $(this);
        if (clickedButton.hasClass('wc_new_comment')) {
            data = 'newCommentIds=' + wpdiscuzCommentArray.join();
        } else {
            data = 'newCommentIds=' + wpdiscuzReplyArray.join();
        }

        getAjaxObject('updateOnClick', data).done(function (response) {
            try {
                var obj = $.parseJSON(response);
                liveUpdateImmediately(obj);
                if (clickedButton.hasClass('wc_new_comment')) {
                    wpdiscuzCommentArray = [];
                    $('.wc_new_comment').hide();
                } else {
                    wpdiscuzReplyArray = [];
                    $('.wc_new_reply').hide();
                }
            } catch (e) {
                console.log(e);
            }
            addTooltipster();
            $('.wpdiscuz-loading-bar').hide();
        });
    });
//============================== LIVE UPDATE ============================== // 
//============================== READ MORE ============================== // 
    $(document).delegate('.wpdiscuz-readmore', 'click', function () {
        var uniqueId = getUniqueID($(this));
        var commentId = getCommentID(uniqueId);
        var data = 'commentId=' + commentId;
        getAjaxObject('readMore', data).done(function (response) {
            try {
                var obj = $.parseJSON(response);
                if (obj.code) {
                    $('#comment-' + commentId + ' > .wc-comment-text').html(' ' + obj.message);
                    $('#wpdiscuz-readmore-' + uniqueId).remove();
                }
            } catch (e) {
                console.log(e);
            }
            $('.wpdiscuz-loading-bar').hide();
        });
    });
//============================== READ MORE ============================== // 
//============================== FUNCTIONS ============================== //

    function wpdiscuzSetCommentMsg(field, messageKey, message, isFormError) {
        var msgContainer;
        var parentContainer;
        if (isFormError) {
            parentContainer = field.parents('.wc-form-wrapper');
        } else {
            parentContainer = field.closest('.wc-comment');
        }
        msgContainer = parentContainer.children('.wpdiscuz-comment-message');
        msgContainer.addClass(messageKey);
        msgContainer.html(message);
        msgContainer.show().delay(4000).fadeOut(1000, function () {
            msgContainer.removeClass();
            msgContainer.addClass('wpdiscuz-comment-message');
            msgContainer.html('');
        });

    }

    function cloneSecondaryForm(field) {
        var uniqueId = getUniqueID(field, 0);
        $('#wpdiscuz_form_anchor-' + uniqueId).before(replaceUniqueId(uniqueId));
        var secondaryFormWrapper = $('#wc-secondary-form-wrapper-' + uniqueId);
        secondaryFormWrapper.slideToggle(700, function () {
            field.addClass('wpdiscuz-clonned');
        });
        changeCaptchaImage($('.wc_captcha_refresh_img', secondaryFormWrapper));
    }

    function replaceUniqueId(uniqueId) {
        var secondaryForm = $('#wpdiscuz_hidden_secondary_form').html();
        return secondaryForm.replace(/wpdiscuzuniqueid/g, uniqueId);
    }

    function getUniqueID(field, isMain) {
        var fieldID = '';
        if (isMain) {
            fieldID = field.parents('.wc-main-form-wrapper').attr('id');
        } else {
            fieldID = field.parents('.wc-comment').attr('id');
        }
        var uniqueID = fieldID.substring(fieldID.lastIndexOf('-') + 1);
        return uniqueID;
    }

    function getCommentID(uniqueID) {
        return uniqueID.substring(0, uniqueID.indexOf('_'));
    }

    function getLastParentID() {
        var url = $('.wc-load-more-link').attr("href");
        return url.substring(url.lastIndexOf('=') + 1);
    }

    function setLastParentID(lastParentID) {
        var url = $('.wc-load-more-link').attr("href");
        $('.wc-load-more-link').attr("href", url.replace(/[\d]+$/m, lastParentID));
        if (commentListLoadType != 2) {
            $('.wpdiscuz-comment-pagination').show();
        }
    }


    function getCommentDepth(field) {
        var fieldClasses = field.attr('class');
        var classesArray = fieldClasses.split(' ');
        var depth = '';
        $.each(classesArray, function (index, value) {
            if ('wc_comment_level' === getParentDepth(value, false)) {
                depth = getParentDepth(value, true);
            }
        });
        return parseInt(depth) + 1;
    }

    function getParentDepth(depthValue, isNumberPart) {
        var depth = '';
        if (isNumberPart) {
            depth = depthValue.substring(depthValue.indexOf('-') + 1);
        } else {
            depth = depthValue.substring(0, depthValue.indexOf('-'));
        }
        return depth;
    }

    function addCommentToTree(parentId, comment) {
        if (parentId == 0) {
            $('.wc-thread-wrapper').prepend(comment);
        } else {
            var parentUniqueId = getUniqueID($('#comment-' + parentId), 0);
            $('#wpdiscuz_form_anchor-' + parentUniqueId).after(comment);
        }
    }

    function getVisibleCommentIds() {
        var uniqueId;
        var commentId;
        var visibleCommentIds = '';
        $('.wc-comment-right').each(function () {
            uniqueId = getUniqueID($(this), 0);
            commentId = getCommentID(uniqueId);
            visibleCommentIds += commentId + ',';
        });
        return visibleCommentIds;
    }

    function addTooltipster() {
        $('.wc-comment-img-link').tooltipster({
            trigger: 'click',
            contentAsHTML: true,
            interactive: true,
            multiple: true
        });
        $('.wc_tooltipster').tooltipster({offsetY: 2, multiple: true});
    }

    function loginButtonsClone() {
        if ($('.wc_social_plugin_wrapper .wp-social-login-provider-list').length) {
            $('.wc_social_plugin_wrapper .wp-social-login-provider-list').clone().prependTo('#wpdiscuz_hidden_secondary_form > .wc-form-wrapper >  .wc-secondary-forms-social-content');
        } else if ($('.wc_social_plugin_wrapper .the_champ_login_container').length) {
            $('.wc_social_plugin_wrapper .the_champ_login_container').clone().prependTo('#wpdiscuz_hidden_secondary_form > .wc-form-wrapper >  .wc-secondary-forms-social-content');
        } else if ($('.wc_social_plugin_wrapper .social_connect_form').length) {
            $('.wc_social_plugin_wrapper .social_connect_form').clone().prependTo('#wpdiscuz_hidden_secondary_form > .wc-form-wrapper >  .wc-secondary-forms-social-content');
        } else if ($('.wc_social_plugin_wrapper .oneall_social_login_providers').length) {
            $('.wc_social_plugin_wrapper .oneall_social_login .oneall_social_login_providers').clone().prependTo('#wpdiscuz_hidden_secondary_form > .wc-form-wrapper >  .wc-secondary-forms-social-content');
        }
    }

    function displayShowHideReplies() {
        $('#wcThreadWrapper .wc-comment').each(function (i) {
            if ($('> .wc-reply', this).length) {
                $('> .wc-comment-right .wc-comment-footer .wc-toggle', this).removeClass('wpdiscuz-hidden');
            }
        });
    }

    /**
     * @param {type} action the action key 
     * @param {type} data the request properties
     * @returns {jqXHR}
     */
    function getAjaxObject(action, data) {
        if (action !== 'liveUpdate') {
            $('.wpdiscuz-loading-bar').show();
        }
        data += '&postId=' + wpdiscuzPostId;
        return $.ajax({
            type: 'POST',
            url: wpdiscuz_ajax_obj.url,
            data: {
                wpdiscuzAjaxData: data,
                action: action
            }
        });
    }
    //============================== FUNCTIONS ============================== // 
});