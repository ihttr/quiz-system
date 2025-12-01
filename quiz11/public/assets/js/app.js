class QuizApp {
    constructor() {
        this.initializeEventListeners();
    }
    
    initializeEventListeners() {
        // Theme toggle
        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', () => this.toggleTheme());
        }
        
        // Add any other global event listeners here
    }
    
    toggleTheme() {
        const formData = new FormData();
        formData.append('toggle_theme', true);
        
        fetch('', {
            method: 'POST',
            body: formData
        }).then(() => {
            location.reload();
        });
    }
    
    // Global utility functions
    showLoading() {
        // Show loading indicator
        const loading = document.createElement('div');
        loading.className = 'loading-overlay';
        loading.innerHTML = '<div class="spinner"></div>';
        document.body.appendChild(loading);
    }
    
    hideLoading() {
        const loading = document.querySelector('.loading-overlay');
        if (loading) {
            loading.remove();
        }
    }
    
    showMessage(message, type = 'info') {
        const messageDiv = document.createElement('div');
        messageDiv.className = `alert ${type}`;
        messageDiv.textContent = message;
        
        document.querySelector('.container').prepend(messageDiv);
        
        setTimeout(() => {
            messageDiv.remove();
        }, 5000);
    }
}

// Initialize the app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.quizApp = new QuizApp();
});

// Utility functions for API calls
const ApiClient = {
    async get(url) {
        try {
            const response = await fetch(url);
            return await response.json();
        } catch (error) {
            console.error('API GET Error:', error);
            throw error;
        }
    },
    
    async post(url, data) {
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            return await response.json();
        } catch (error) {
            console.error('API POST Error:', error);
            throw error;
        }
    }
};