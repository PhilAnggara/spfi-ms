<div id="screen-message-overlay" class="sm-overlay" hidden aria-hidden="true">
    <div class="sm-overlay__backdrop"></div>
    <div class="sm-overlay__panel" role="dialog" aria-modal="true" aria-labelledby="sm-overlay-title">
        <div class="sm-overlay__accent"></div>
        <div class="sm-overlay__header">
            <span id="sm-overlay-badge" class="sm-overlay__badge" hidden>Permanent</span>
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
            <label for="sm-overlay-reply-input" class="sm-overlay__reply-label">Your reply</label>
            <textarea id="sm-overlay-reply-input" class="sm-overlay__reply-input" rows="3" maxlength="2000"></textarea>
            <button type="button" id="sm-overlay-reply-submit" class="sm-overlay__btn sm-overlay__btn--secondary" hidden>
                Send reply
            </button>
        </div>
        <div class="sm-overlay__actions">
            <button type="button" id="sm-overlay-close" class="sm-overlay__btn sm-overlay__btn--primary" hidden>
                Close
            </button>
            <p id="sm-overlay-permanent-note" class="sm-overlay__note" hidden>
                This message stays on screen until the sender deactivates it.
            </p>
        </div>
    </div>
</div>
