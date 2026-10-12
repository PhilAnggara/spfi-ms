<div id="screen-message-overlay" class="sm-overlay" hidden aria-hidden="true" data-theme="default">
    <div class="sm-overlay__backdrop"></div>
    <div class="sm-overlay__panel" role="dialog" aria-modal="true" aria-labelledby="sm-overlay-title">
        <div class="sm-overlay__accent"></div>
        <button type="button" id="sm-overlay-close" class="sm-overlay__close" hidden aria-label="Close">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
        <div class="sm-overlay__header">
            <h2 id="sm-overlay-title" class="sm-overlay__title"></h2>
        </div>
        <div id="sm-overlay-body" class="sm-overlay__body"></div>
        <div id="sm-overlay-timer" class="sm-overlay__timer" hidden>
            <div class="sm-overlay__timer-track">
                <div id="sm-overlay-timer-bar" class="sm-overlay__timer-bar"></div>
            </div>
            <span id="sm-overlay-timer-text" class="sm-overlay__timer-text"></span>
        </div>
        <div id="sm-overlay-reply" class="sm-overlay__reply" hidden>
            <div id="sm-overlay-reply-form" class="sm-overlay__reply-card">
                <div class="sm-overlay__reply-head">
                    <span class="sm-overlay__reply-icon" aria-hidden="true">
                        <i class="fa-light fa-comment-dots"></i>
                    </span>
                    <label for="sm-overlay-reply-input" class="sm-overlay__reply-label">Reply</label>
                </div>
                <textarea id="sm-overlay-reply-input" class="sm-overlay__reply-input" rows="2" maxlength="2000" placeholder="Write your reply…"></textarea>
                <div class="sm-overlay__reply-actions">
                    <button type="button" id="sm-overlay-reply-submit" class="sm-overlay__btn sm-overlay__btn--send">
                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                        Send
                    </button>
                </div>
            </div>
            <div id="sm-overlay-reply-sent" class="sm-overlay__reply-card sm-overlay__reply-card--sent" hidden>
                <div class="sm-overlay__reply-head">
                    <span class="sm-overlay__reply-icon sm-overlay__reply-icon--sent" aria-hidden="true">
                        <i class="fa-solid fa-check"></i>
                    </span>
                    <div class="sm-overlay__reply-label">Sent</div>
                </div>
                <div id="sm-overlay-reply-sent-body" class="sm-overlay__reply-sent"></div>
            </div>
        </div>
    </div>
</div>
