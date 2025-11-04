// WT_Project2/js/typing-indicator.js

class TypingIndicator {
    constructor(chatType, chatId) {
        this.chatType = chatType;
        this.chatId = chatId;
        this.isTyping = false;
        this.pollInterval = null;
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.startPolling();
    }

    setupEventListeners() {
        const msgInput = document.getElementById('messageText');
        if (!msgInput) return;

        let timer;
        msgInput.addEventListener('input', () => {
            if (!this.isTyping && msgInput.value.trim() !== '') {
                this.setTypingStatus(true);
            }
            clearTimeout(timer);
            timer = setTimeout(() => {
                this.setTypingStatus(false);
            }, 1000);
        });

        msgInput.addEventListener('blur', () => {
            this.setTypingStatus(false);
        });

        const form = document.getElementById('messageForm');
        if (form) {
            form.addEventListener('submit', () => {
                this.setTypingStatus(false);
            });
        }
    }

    async setTypingStatus(typing) {
        if (this.isTyping === typing) return;
        this.isTyping = typing;
        try {
            await fetch(`../php/chat/update_typing_status.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    chat_type: this.chatType,
                    chat_id: this.chatId,
                    is_typing: typing ? 1 : 0
                })
            });
        } catch {
            // ignore network errors for typing status
        }
    }

    async fetchTypingUsers() {
        try {
            const resp = await fetch(`../php/chat/get_typing_users.php?chat_type=${this.chatType}&chat_id=${this.chatId}`);
            const text = await resp.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch {
                // response was not valid JSON (likely HTML), ignore
                return;
            }
            if (data.success && Array.isArray(data.typing_users)) {
                this.updateTypingDisplay(data.typing_users);
            }
        } catch {
            // ignore fetch errors
        }
    }

    updateTypingDisplay(users) {
        const container = document.getElementById('typingIndicator');
        if (!container) return;
        if (users.length === 0) {
            container.innerHTML = '';
            container.style.display = 'none';
            return;
        }
        let text;
        if (users.length === 1) {
            text = `${users[0].name} is typing...`;
        } else if (users.length === 2) {
            text = `${users[0].name} and ${users[1].name} are typing...`;
        } else {
            text = `${users[0].name} and ${users.length - 1} others are typing...`;
        }
        container.innerHTML = `
            <div class="typing-indicator-content">
                <div class="typing-dots">
                    <span class="typing-dot"></span>
                    <span class="typing-dot"></span>
                    <span class="typing-dot"></span>
                </div>
                <span class="typing-text">${text}</span>
            </div>`;
        container.style.display = 'block';
    }

    startPolling() {
        this.pollInterval = setInterval(() => {
            this.fetchTypingUsers();
        }, 2000);
    }

    destroy() {
        clearInterval(this.pollInterval);
        this.setTypingStatus(false);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const { currentChatType, currentChatId } = window;
    if (currentChatType && currentChatId) {
        window.typingIndicator = new TypingIndicator(currentChatType, currentChatId);
    }
});

window.addEventListener('beforeunload', () => {
    if (window.typingIndicator) {
        window.typingIndicator.destroy();
    }
});
