// Hide all modals immediately on script load to prevent flicker
window.addEventListener('DOMContentLoaded', () => {
    // Remove query string from URL if present (prevents form data in address bar)
    if (window.location.search.length > 0) {
        window.history.replaceState({}, document.title, window.location.pathname);
    }
    // Defensive: Hide all modals before anything else
    document.querySelectorAll('.modal').forEach(modal => {
        modal.style.display = 'none';
        modal.setAttribute('hidden', '');
    });
    // If app is initialized, call its hideAllModals as well
    app && app.hideAllModals && app.hideAllModals();
});

// Global error handler for debugging
window.addEventListener('error', function(event) {
    console.error('Global JS Error:', event.error || event.message, event);
});

// --- Modal Management ---
function hideAllModals() {
    ['user-modal', 'task-modal', 'delete-user-modal'].forEach(id => {
        const modal = document.getElementById(id);
        if (modal) {
            modal.hidden = true;
            modal.style.display = 'none';
        }
    });
}

function showUserModal() {
    hideAllModals();
    const modal = document.getElementById('user-modal');
    if (modal) {
        modal.hidden = false;
        modal.style.display = 'flex';
    }
}
function hideUserModal() {
    const modal = document.getElementById('user-modal');
    if (modal) {
        modal.hidden = true;
        modal.style.display = 'none';
    }
}
function showTaskModal(task = null) {
    hideAllModals();
    const modal = document.getElementById('task-modal');
    if (modal) {
        modal.hidden = false;
        modal.style.display = 'flex';
        if (task && window.app) {
            window.app.editingTaskId = task.id;
            window.app.loadTaskComments(task.id);
            // Show only the relevant form
            const userForm = document.getElementById('user-comment-form');
            const adminForm = document.getElementById('admin-comment-form');
            if (userForm) userForm.style.display = (window.app.currentUser && window.app.currentUser.role === 'user') ? 'flex' : 'none';
            if (adminForm) adminForm.style.display = (window.app.currentUser && window.app.currentUser.role === 'admin') ? 'flex' : 'none';
        } else if (window.app) {
            window.app.editingTaskId = null;
            window.app.renderTaskComments([]);
            window.app.removeCommentsSection();
        }
        // Reset comment inputs
        const userInput = document.getElementById('user-comment-input');
        if (userInput) userInput.value = '';
        const adminInput = document.getElementById('admin-comment-input');
        if (adminInput) adminInput.value = '';
    }
}
function hideTaskModal() {
    const modal = document.getElementById('task-modal');
    if (modal) {
        modal.hidden = true;
        modal.style.display = 'none';
    }
}
function showDeleteUserModal() {
    hideAllModals();
    const modal = document.getElementById('delete-user-modal');
    if (modal) {
        modal.hidden = false;
        modal.style.display = 'flex';
    }
}
function hideDeleteUserModal() {
    const modal = document.getElementById('delete-user-modal');
    if (modal) {
        modal.hidden = true;
        modal.style.display = 'none';
    }
}

// --- Bind modal events on DOMContentLoaded ---
window.addEventListener('DOMContentLoaded', () => {
    hideAllModals();
    // User modal
    const addUserBtn = document.getElementById('add-user-btn');
    if (addUserBtn) addUserBtn.addEventListener('click', showUserModal);
    const closeUserModal = document.getElementById('close-user-modal');
    if (closeUserModal) closeUserModal.addEventListener('click', hideUserModal);
    const cancelUser = document.getElementById('cancel-user');
    if (cancelUser) cancelUser.addEventListener('click', hideUserModal);
    // Restore user form submit handler with debug log
    const userForm = document.getElementById('user-form');
    if (userForm) userForm.addEventListener('submit', function(e) {
        e.preventDefault();
        console.log('User form submit handler running (JS interception works)');
        if (window.app && typeof window.app.saveUser === 'function') {
            window.app.saveUser().then(hideUserModal);
        }
    });
    // Task modal
    const addTaskBtn = document.getElementById('add-task-btn');
    if (addTaskBtn) addTaskBtn.addEventListener('click', showTaskModal);
    const closeTaskModal = document.getElementById('close-task-modal');
    if (closeTaskModal) closeTaskModal.addEventListener('click', hideTaskModal);
    const cancelTask = document.getElementById('cancel-task');
    if (cancelTask) cancelTask.addEventListener('click', hideTaskModal);
    // Restore task form submit handler
    const taskForm = document.getElementById('task-form');
    if (taskForm) taskForm.addEventListener('submit', function(e) {
        e.preventDefault();
        if (window.app && typeof window.app.saveTask === 'function') {
            window.app.saveTask();
        }
    });
    // Delete user modal
    const closeDeleteUserModal = document.getElementById('close-delete-user-modal');
    if (closeDeleteUserModal) closeDeleteUserModal.addEventListener('click', hideDeleteUserModal);
    const cancelDeleteUser = document.getElementById('cancel-delete-user');
    if (cancelDeleteUser) cancelDeleteUser.addEventListener('click', hideDeleteUserModal);
    // Clicking outside modal closes it
    window.addEventListener('click', (e) => {
        const modals = ['user-modal', 'task-modal', 'delete-user-modal'];
        modals.forEach(id => {
            const modal = document.getElementById(id);
            if (modal && e.target === modal) {
                modal.hidden = true;
                modal.style.display = 'none';
            }
        });
    });
});
// --- End Modal Management ---

// Task Management System Frontend
class TaskManager {
    constructor() {
        this.baseUrl = '/api';
        this.currentUser = null;
        this.users = [];
        this.tasks = [];
        this.userIdToDelete = null;
        this._lastTasksHash = '';
        this.init();
        this.initTasksSSE();
    }

    // SSE for subtle real-time tasks updates
    initTasksSSE() {
        if (window.EventSource) {
            let evtSource = null;
            const startSSE = () => {
                if (evtSource) evtSource.close();
                if (!this.currentUser || this.currentUser.role !== 'user') return;
                evtSource = new EventSource('/api/user/tasks_sse.php');
                evtSource.onmessage = (e) => {
                    try {
                        const resp = JSON.parse(e.data);
                        if (resp && resp.tasks) {
                            const newHash = this.hashTasks(resp.tasks);
                            if (newHash !== this._lastTasksHash) {
                                this.tasks = resp.tasks;
                                this._lastTasksHash = newHash;
                                this.animateTasksTableUpdate();
                            }
                        }
                    } catch (err) {}
                };
                evtSource.onerror = () => {
                    // Try to reconnect after a delay
                    setTimeout(startSSE, 5000);
                };
            };
            startSSE();
        }
    }

    // Simple hash function for tasks array
    hashTasks(tasks) {
        if (!tasks || !tasks.length) return '';
        return tasks.map(t => `${t.id}:${t.status}:${t.updated_at || ''}`).join('|');
    }

    // Animate table update
    animateTasksTableUpdate() {
        const tbody = document.querySelector('#tasks-table tbody');
        if (!tbody) return;
        tbody.style.transition = 'opacity 0.3s';
        tbody.style.opacity = '0.3';
        setTimeout(() => {
            this.renderTasksTable();
            tbody.style.opacity = '1';
        }, 200);
    }

    // --- SSE for live comments ---
    startCommentsSSE(taskId) {
        if (this._commentsEvtSource) {
            this._commentsEvtSource.close();
            this._commentsEvtSource = null;
        }
        if (!window.EventSource || !taskId) return;
        const url = `/api/user/task_comments_sse.php?task_id=${encodeURIComponent(taskId)}`;
        let lastHash = '';
        this._commentsEvtSource = new EventSource(url);
        this._commentsEvtSource.onmessage = (e) => {
            try {
                const resp = JSON.parse(e.data);
                if (resp && resp.comments) {
                    const newHash = this.hashComments(resp.comments);
                    if (newHash !== lastHash) {
                        lastHash = newHash;
                        this.renderTaskComments(resp.comments);
                        const list = document.getElementById('user-comments-list');
                        if (list) {
                            list.style.transition = 'opacity 0.3s';
                            list.style.opacity = '0.3';
                            setTimeout(() => { list.style.opacity = '1'; }, 200);
                        }
                    }
                }
            } catch (err) {}
        };
        this._commentsEvtSource.onerror = () => {
            setTimeout(() => this.startCommentsSSE(taskId), 5000);
        };
    }
    stopCommentsSSE() {
        if (this._commentsEvtSource) {
            this._commentsEvtSource.close();
            this._commentsEvtSource = null;
        }
    }
    // Hash for comments array
    hashComments(comments) {
        if (!comments || !comments.length) return '';
        return comments.map(c => `${c.id}:${c.comment}:${c.created_at || ''}`).join('|');
    }
    // REMOVE: Modal management functions and event listeners
    // Placeholder for modal logic to be reimplemented after troubleshooting
    // function hideAllModals() { /* removed */ }
    // function showUserModal(user) { /* removed */ }
    // function hideUserModal() { /* removed */ }
    // function showTaskModal(task) { /* removed */ }
    // function hideTaskModal() { /* removed */ }
    // function showDeleteUserModal() { /* removed */ }
    // function hideDeleteUserModal() { /* removed */ }
    // In bindEvents(), remove all modal-related event listeners

    init() {
        this.bindEvents();
        this.checkAuth();
    }

    bindEvents() {
        // Login form
        document.getElementById('login-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.login();
        });

        // Logout
        document.getElementById('logout-btn').addEventListener('click', () => {
            this.logout();
        });

        // User modal events
        // document.getElementById('add-user-btn').addEventListener('click', () => {
        //     this.showUserModal();
        // });

        // document.getElementById('close-user-modal').addEventListener('click', () => {
        //     this.hideUserModal();
        // });

        // document.getElementById('cancel-user').addEventListener('click', () => {
        //     this.hideUserModal();
        // });

        // document.getElementById('user-form').addEventListener('submit', (e) => {
        //     e.preventDefault();
        //     this.saveUser();
        // });

        // Task modal events
        // document.getElementById('add-task-btn').addEventListener('click', () => {
        //     this.showTaskModal();
        // });

        // document.getElementById('close-task-modal').addEventListener('click', () => {
        //     this.hideTaskModal();
        // });

        // document.getElementById('cancel-task').addEventListener('click', () => {
        //     this.hideTaskModal();
        // });

        // document.getElementById('task-form').addEventListener('submit', (e) => {
        //     e.preventDefault();
        //     this.saveTask();
        // });

        // Close modals when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
            }
        });

        // Delete user modal events
        document.getElementById('close-delete-user-modal').addEventListener('click', () => {
            this.hideDeleteUserModal();
        });
        document.getElementById('cancel-delete-user').addEventListener('click', () => {
            this.hideDeleteUserModal();
        });
        const confirmDeleteUser = document.getElementById('confirm-delete-user');
        if (confirmDeleteUser) confirmDeleteUser.addEventListener('click', function() {
            if (window.app && typeof window.app.deleteUserConfirmed === 'function' && window.app.userIdToDelete) {
                window.app.deleteUserConfirmed(window.app.userIdToDelete);
                window.app.userIdToDelete = null;
            }
        });
    }

    async apiCall(endpoint, method = 'GET', data = null, suppressErrorToast = false) {
        this.showLoading();
        try {
            const options = {
                method,
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
            };
            if (data) {
                options.body = JSON.stringify(data);
            }
            
            console.log(`Making ${method} request to ${endpoint}`, data ? 'with data' : 'without data');
            
            const response = await fetch(endpoint, options);
            console.log(`Response status: ${response.status} ${response.statusText}`);
            
            // Check if response has content
            const responseText = await response.text();
            console.log('Response text:', responseText);
            
            let result;
            try {
                // Try to parse as JSON
                result = responseText ? JSON.parse(responseText) : {};
            } catch (jsonError) {
                console.error('JSON parsing error:', jsonError);
                console.error('Response text that failed to parse:', responseText);
                
                // Create a structured error response
                result = {
                    error: 'Server returned invalid JSON response',
                    details: responseText.substring(0, 200) + (responseText.length > 200 ? '...' : ''),
                    status: response.status,
                    statusText: response.statusText
                };
            }
            
            this.hideLoading();
            
            if (!response.ok) {
                const errorMessage = result.error || result.message || `HTTP ${response.status}: ${response.statusText}`;
                if (!suppressErrorToast) {
                    this.showToast(errorMessage, 'error');
                }
                throw new Error(errorMessage);
            }
            
            return result;
        } catch (error) {
            this.hideLoading();
            console.error('API call error:', error);
            
            if (!suppressErrorToast) {
                const errorMessage = error.message || 'Network error occurred';
                this.showToast(errorMessage, 'error');
            }
            throw error;
        }
    }

    async checkAuth() {
        try {
            const result = await this.apiCall('/api/auth/check.php', 'POST', null, true);
            if (result && result.user) {
                this.currentUser = result.user;
                this.showDashboard();
            } else {
                hideAllModals(); // Hide all modals if not authenticated
                this.showLogin();
            }
        } catch (error) {
            hideAllModals(); // Hide all modals on error
            this.showLogin();
        }
    }

    async login() {
        const username = document.getElementById('username').value;
        const password = document.getElementById('password').value;
        try {
            const result = await this.apiCall('/api/auth/login.php', 'POST', { username, password });
            this.currentUser = result.user;
            this.showToast('Login successful!', 'success');
            this.showDashboard();
        } catch (error) {
            // Error already handled in apiCall
        }
    }

    async logout() {
        try {
            await this.apiCall('/api/auth/logout.php', 'POST');
            this.currentUser = null;
            
            // Show success toast and wait a moment before transitioning
            this.showToast('Logged out successfully!', 'success');
            
            // Also show a temporary message in the login area
            const loginBox = document.querySelector('.login-box');
            if (loginBox) {
                const successMsg = document.createElement('div');
                successMsg.style.cssText = `
                    background: #d4edda;
                    color: #155724;
                    padding: 10px;
                    margin-bottom: 15px;
                    border-radius: 5px;
                    text-align: center;
                    font-weight: 600;
                    border: 1px solid #c3e6cb;
                `;
                successMsg.textContent = '✅ Logged out successfully!';
                loginBox.insertBefore(successMsg, loginBox.firstChild);
                
                // Remove the message after 3 seconds
                setTimeout(() => {
                    if (successMsg.parentNode) {
                        successMsg.remove();
                    }
                }, 3000);
            }
            
            // Wait 1.5 seconds to ensure toast is visible
            await new Promise(resolve => setTimeout(resolve, 1500));
            
            hideAllModals(); // Hide all modals on logout
            this.showLogin();
        } catch (error) {
            // Error already handled in apiCall
            hideAllModals(); // Defensive: Hide all modals on error
        }
    }

    showLogin() {
        document.getElementById('login-container').style.display = 'block';
        document.getElementById('dashboard-container').style.display = 'none';
        document.getElementById('navbar').style.display = 'none';
        hideAllModals(); // Always hide all modals when showing login
    }

    // Remove startAutoRefresh for users, keep for admin only
    startAutoRefresh() {
        if (this._autoRefreshInterval) clearInterval(this._autoRefreshInterval);
        if (this.currentUser && this.currentUser.role === 'admin') {
            this._autoRefreshInterval = setInterval(async () => {
                await this.loadAllTasks();
            }, 10000); // 10 seconds
        }
        // Update time-remaining display every minute for smooth countdown
        if (this._timeUpdateInterval) clearInterval(this._timeUpdateInterval);
        this._timeUpdateInterval = setInterval(() => {
            this.renderTasksTable();
        }, 60000); // 1 minute
    }

    // In showDashboard, only call startAutoRefresh for admin
    async showDashboard() {
        document.getElementById('login-container').style.display = 'none';
        document.getElementById('dashboard-container').style.display = 'block';
        document.getElementById('navbar').style.display = 'block';
        const userNameElement = document.getElementById('user-name');
        userNameElement.textContent = this.currentUser.username;
        if (this.currentUser.role === 'admin') {
            document.getElementById('admin-controls').style.display = 'block';
            await this.loadUsers();
            await this.loadAllTasks();
            this.startAutoRefresh();
        } else {
            document.getElementById('admin-controls').style.display = 'none';
            await this.loadUserTasks();
        }
        await this.loadDashboardStats();
    }

    async loadDashboardStats() {
        try {
            let result;
            if (this.currentUser.role === 'admin') {
                result = await this.apiCall('/api/admin/dashboard.php');
            } else {
                result = await this.apiCall('/api/user/dashboard.php');
            }
            const stats = result.stats;
            
            console.log('Dashboard stats received:', stats);
            
            // Set stats with debugging and inline styles for visibility
            const totalTasksEl = document.getElementById('total-tasks');
            const pendingTasksEl = document.getElementById('pending-tasks');
            const inProgressTasksEl = document.getElementById('in-progress-tasks');
            const completedTasksEl = document.getElementById('completed-tasks');
            
            if (totalTasksEl) {
                totalTasksEl.textContent = stats.total_tasks;
                totalTasksEl.style.color = '#000000';
                totalTasksEl.style.fontWeight = '700';
                console.log('Set total tasks:', stats.total_tasks);
            }
            
            if (pendingTasksEl) {
                pendingTasksEl.textContent = stats.pending_tasks;
                pendingTasksEl.style.color = '#000000';
                pendingTasksEl.style.fontWeight = '700';
                console.log('Set pending tasks:', stats.pending_tasks);
            }
            
            if (inProgressTasksEl) {
                inProgressTasksEl.textContent = stats.in_progress_tasks;
                inProgressTasksEl.style.color = '#000000';
                inProgressTasksEl.style.fontWeight = '700';
                console.log('Set in progress tasks:', stats.in_progress_tasks);
            }
            
            if (completedTasksEl) {
                completedTasksEl.textContent = stats.completed_tasks;
                completedTasksEl.style.color = '#000000';
                completedTasksEl.style.fontWeight = '700';
                console.log('Set completed tasks:', stats.completed_tasks);
            }
            
            // Ensure stat card labels are visible
            const statLabels = document.querySelectorAll('.stat-info p');
            statLabels.forEach(label => {
                label.style.color = '#000000';
                label.style.fontWeight = '600';
                label.style.fontSize = '1rem';
                label.style.textTransform = 'uppercase';
                label.style.letterSpacing = '0.05em';
            });
            
            console.log('Applied visibility styles to stat cards');
        } catch (error) {
            console.error('Error loading dashboard stats:', error);
            // Error already handled in apiCall
        }
    }

    async loadUsers() {
        try {
            const result = await this.apiCall('/api/admin/users.php', 'GET');
            this.users = result.users;
            this.renderUsersTable();
            this.updateUserSelect();
        } catch (error) {
            // Error already handled in apiCall
        }
    }

    async loadAllTasks() {
        try {
            const result = await this.apiCall('/api/admin/tasks.php', 'GET');
            this.tasks = result.tasks;
            this.renderTasksTable();
        } catch (error) {}
    }

    async loadUserTasks() {
        try {
            const result = await this.apiCall('/api/user/tasks.php', 'GET');
            this.tasks = result.tasks;
            this.renderTasksTable();
        } catch (error) {}
    }

    renderUsersTable() {
        const tbody = document.querySelector('#users-table tbody');
        tbody.innerHTML = '';

        console.log('Rendering users table with data:', this.users);

        this.users.forEach(user => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td style="color: var(--text-emphasis); font-weight: var(--font-weight-medium);">${user.id}</td>
                <td style="color: var(--text-emphasis); font-weight: var(--font-weight-medium);">${user.username}</td>
                <td style="color: var(--text-emphasis); font-weight: var(--font-weight-medium);">${user.email}</td>
                <td style="color: var(--text-emphasis); font-weight: var(--font-weight-medium);">${user.role}</td>
                <td class="action-buttons">
                    <button class="btn btn-sm btn-primary" onclick="app.editUser(${user.id})">Edit</button>
                    <button class="btn btn-sm btn-danger" onclick="app.deleteUser(${user.id})">Delete</button>
                </td>
            `;
            tbody.appendChild(row);
        });
    }

    // Add after renderTasksTable
    addTaskTableFilters() {
        const container = document.querySelector('.tasks-section');
        if (!container) return;
        
        let filterBar = document.getElementById('task-filter-bar');
        if (!filterBar) {
            // Create filter bar if it doesn't exist
            filterBar = document.createElement('div');
            filterBar.id = 'task-filter-bar';
            filterBar.style.marginBottom = '1em';
            container.insertBefore(filterBar, container.querySelector('h2'));
        }
        
        // Only populate if it's empty
        if (!filterBar.innerHTML.trim()) {
            filterBar.innerHTML = `
                <button class="btn btn-sm btn-secondary" data-filter="active">Active</button>
                <button class="btn btn-sm btn-secondary" data-filter="completed">Completed</button>
                <button class="btn btn-sm btn-secondary" data-filter="expired">Expired</button>
                <button class="btn btn-sm btn-secondary" data-filter="all">All</button>
            `;
        }
        
        filterBar.querySelectorAll('button').forEach(btn => {
            btn.onclick = (e) => {
                this.currentTaskFilter = btn.getAttribute('data-filter');
                this.renderTasksTable();
                filterBar.querySelectorAll('button').forEach(b => b.classList.remove('btn-primary'));
                btn.classList.add('btn-primary');
            };
        });
        
        // Default filter - with null check
        if (!this.currentTaskFilter) {
            this.currentTaskFilter = 'active';
            const activeButton = filterBar.querySelector('button[data-filter="active"]');
            if (activeButton) {
                activeButton.classList.add('btn-primary');
            }
        }
    }

    filterTasks(tasks) {
        const today = new Date().toISOString().slice(0, 10);
        switch (this.currentTaskFilter) {
            case 'completed':
                return tasks.filter(t => t.status === 'Completed');
            case 'expired':
                return tasks.filter(t => t.status !== 'Completed' && t.deadline && t.deadline < today);
            case 'active':
                return tasks.filter(t => t.status !== 'Completed' && (!t.deadline || t.deadline >= today));
            case 'all':
            default:
                return tasks;
        }
    }

    // Helper to calculate time remaining
    getTimeRemaining(deadline) {
        if (!deadline) return '';
        const now = new Date();
        const end = new Date(deadline);
        const diffMs = end - now;
        if (isNaN(diffMs)) return '';
        if (diffMs < 0) return 'Expired';
        const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
        const diffHours = Math.floor((diffMs % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        let str = '';
        if (diffDays > 0) str += `${diffDays}d `;
        str += `${diffHours}h`;
        return str.trim();
    }

    renderTasksTable() {
        this.addTaskTableFilters();
        const tbody = document.querySelector('#tasks-table tbody');
        if (!tbody) return;
        // Fade out
        tbody.style.transition = 'opacity 0.3s';
        tbody.style.opacity = '0.3';
        const scrollTop = tbody.scrollTop;
        setTimeout(() => {
            tbody.innerHTML = '';
            const filtered = this.filterTasks(this.tasks);
            filtered.forEach(task => {
                const row = document.createElement('tr');
                const statusClass = task.status.toLowerCase().replace(' ', '-');
                const timeRemaining = this.getTimeRemaining(task.deadline);
                row.innerHTML = `
                    <td style="color: var(--text-emphasis); font-weight: var(--font-weight-medium);">${task.id}</td>
                    <td style="color: var(--text-emphasis); font-weight: var(--font-weight-medium);">${task.title}</td>
                    <td style="color: var(--text-emphasis); font-weight: var(--font-weight-medium);">${task.description || 'No description'}</td>
                    <td style="color: var(--text-emphasis); font-weight: var(--font-weight-medium);">${task.assigned_to_name}</td>
                    <td><span class="status-badge status-${statusClass}">${task.status}</span></td>
                    <td style="color: var(--text-emphasis); font-weight: var(--font-weight-medium);">${task.deadline || ''}${timeRemaining ? `<br><span style='color:var(--text-muted);font-size:0.9em;'>${timeRemaining} left</span>` : ''}</td>
                    <td class="action-buttons">
                        ${this.getTaskActions(task)}
                    </td>
                `;
                row.style.cursor = 'pointer';
                row.onclick = (e) => {
                    if (!e.target.closest('.action-buttons')) {
                        this.openTaskModal(task);
                    }
                };
                tbody.appendChild(row);
            });
            tbody.scrollTop = scrollTop;
            tbody.style.opacity = '1';
        }, 200);
    }

    // Dynamically render comments/feedback section for view/edit mode
    renderCommentsSection() {
        let section = document.getElementById('task-comments-section');
        if (!section) {
            section = document.createElement('div');
            section.id = 'task-comments-section';
            section.className = 'comments-card';
            const modalContent = document.querySelector('#task-modal .modal-content');
            if (modalContent) {
                modalContent.appendChild(section);
            }
        }
        // Header with icon, title, badge, and description
        const comments = this.currentTaskComments || [];
        const badge = `<span class="comments-badge">${comments.length}</span>`;
        section.innerHTML = `
            <div class="comments-header">
                <span class="comments-icon" aria-hidden="true">💬</span>
                <span class="comments-title">Comments</span>
                ${badge}
                <span class="comments-desc">Leave feedback or ask a question about this task.</span>
            </div>
            <div id="user-comments-list" class="comments-list" aria-live="polite">
                <!-- Comments will be rendered here -->
            </div>
            <form id="user-comment-form" class="comment-form" autocomplete="off" aria-label="Add a comment">
                <input type="text" id="user-comment-input" placeholder="Write a comment…" aria-label="Write a comment" required maxlength="500">
                <button type="submit" title="Send" aria-label="Send comment"><span class="icon-plane">✈️</span></button>
            </form>
        `;
        // Render comments (if any)
        this.renderTaskComments(comments);
        // Add submit handler
        const form = section.querySelector('#user-comment-form');
        if (form) {
            form.onsubmit = (e) => {
                e.preventDefault();
                const input = form.querySelector('#user-comment-input');
                if (input && input.value.trim()) {
                    this.addTaskComment(this.editingTaskId, input.value.trim());
                    input.value = '';
                }
            };
            // Keyboard shortcut: Ctrl+Enter to submit
            form.querySelector('#user-comment-input').addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    form.dispatchEvent(new Event('submit', {cancelable: true, bubbles: true}));
                }
            });
        }
    }

    renderTaskComments(comments) {
        this.currentTaskComments = comments;
        const list = document.getElementById('user-comments-list');
        if (!list) return;
        list.innerHTML = '';
        if (!comments || comments.length === 0) {
            list.innerHTML = '<div class="empty-state">No comments yet. Be the first to give feedback!</div>';
            return;
        }
        comments.forEach(comment => {
            const div = document.createElement('div');
            div.className = 'comment';
            // Use a default avatar if not present
            const avatarUrl = comment.avatar_url || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(comment.author || 'User') + '&background=eee&color=516d45&size=64';
            div.innerHTML = `
                <img src="${avatarUrl}" alt="User avatar" class="comment-avatar" loading="lazy">
                <div class="comment-body">
                    <div class="comment-meta">
                        <span class="comment-author">${comment.author || 'User'}</span>
                        <span class="comment-time">${this.timeAgo(comment.created_at)}</span>
                    </div>
                    <div class="comment-text">${this.escapeHTML(comment.text)}</div>
                </div>
            `;
            list.appendChild(div);
        });
    }

    // Helper to escape HTML in comments
    escapeHTML(str) {
        return str.replace(/[&<>"']/g, function(tag) {
            const charsToReplace = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            };
            return charsToReplace[tag] || tag;
        });
    }

    // Helper to show time ago
    timeAgo(dateString) {
        if (!dateString) return '';
        const now = new Date();
        const date = new Date(dateString);
        const diff = Math.floor((now - date) / 1000);
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff/60) + ' min ago';
        if (diff < 86400) return Math.floor(diff/3600) + ' hr ago';
        return date.toLocaleDateString();
    }

    removeCommentsSection() {
        const section = document.getElementById('task-comments-section');
        if (section && section.parentNode) {
            section.parentNode.removeChild(section);
        }
        this.stopCommentsSSE();
    }

    // Open modal for view/edit and load comments
    openTaskModal(task) {
        this.editingTaskId = task.id;
        document.getElementById('task-title').value = task.title;
        document.getElementById('task-title').disabled = true;
        document.getElementById('task-description').value = task.description;
        document.getElementById('task-assigned-to').value = task.assigned_to;
        document.getElementById('task-assigned-to').disabled = true;
        document.getElementById('task-deadline').value = task.deadline;
        document.getElementById('task-deadline').disabled = true;
        document.getElementById('task-modal-title').textContent = 'Task Details';
        this.renderCommentsSection();
        this.startCommentsSSE(task.id);
        showTaskModal(task);
    }

    getTaskActions(task) {
        let actions = '';
        if (this.currentUser.role === 'admin') {
            actions += `
                <button class="btn btn-sm btn-primary" onclick="app.editTask(${task.id})">Edit</button>
                <button class="btn btn-sm btn-danger" onclick="app.deleteTask(${task.id})">Delete</button>
            `;
        }
        // Only users (not admin) can update status
        if (task.assigned_to == this.currentUser.id && this.currentUser.role !== 'admin') {
            actions += `
                <select onchange="app.updateTaskStatus(${task.id}, this.value)" class="btn btn-sm">
                    <option value="">Update Status</option>
                    <option value="Pending" ${task.status === 'Pending' ? 'selected' : ''}>Pending</option>
                    <option value="In Progress" ${task.status === 'In Progress' ? 'selected' : ''}>In Progress</option>
                    <option value="Completed" ${task.status === 'Completed' ? 'selected' : ''}>Completed</option>
                </select>
            `;
        }
        return actions;
    }

    updateUserSelect() {
        const select = document.getElementById('task-assigned-to');
        select.innerHTML = '<option value="">Select User</option>';
        
        this.users.forEach(user => {
            const option = document.createElement('option');
            option.value = user.id;
            option.textContent = `${user.username} (${user.email})`;
            select.appendChild(option);
        });
    }

    // REMOVE: Modal management functions and event listeners
    // Placeholder for modal logic to be reimplemented after troubleshooting
    // function showUserModal(user = null) { /* removed */ }
    // function hideUserModal() { /* removed */ }
    // function showTaskModal(task = null) { /* removed */ }
    // function hideTaskModal() { /* removed */ }

    showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        // Remove all existing toasts before showing a new one
        while (container.firstChild) {
            container.removeChild(container.firstChild);
        }
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        // Create a more structured toast with icon and better formatting
        const icon = type === 'error' ? '⚠️' : type === 'success' ? '✅' : 'ℹ️';
        toast.innerHTML = `
            <div class="toast-content">
                <span class="toast-icon">${icon}</span>
                <span class="toast-message">${message}</span>
            </div>
        `;
        
        // Add specific styling for different toast types
        if (type === 'error') {
            toast.style.backgroundColor = '#fff5f5';
            toast.style.borderLeftColor = '#dc3545';
            toast.style.color = '#721c24';
            toast.style.fontWeight = '600';
            toast.style.border = '2px solid #dc3545';
        } else if (type === 'success') {
            toast.style.backgroundColor = '#f0fff4';
            toast.style.borderLeftColor = '#28a745';
            toast.style.color = '#155724';
            toast.style.fontWeight = '600';
            toast.style.border = '2px solid #28a745';
        }
        
        container.appendChild(toast);
        
        // Auto-remove after 5 seconds for errors, 4 seconds for success, 3 seconds for others
        const duration = type === 'error' ? 5000 : type === 'success' ? 4000 : 3000;
        setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, duration);
        
        // Log to console for debugging
        console.log(`Toast [${type}]:`, message);
    }

    showLoading() {
        document.getElementById('loading').style.display = 'block';
    }

    hideLoading() {
        document.getElementById('loading').style.display = 'none';
    }

    // Add editUser and editTask for modal population
    editUser(id) {
        const user = this.users.find(u => u.id === id);
        if (user) {
            this.editingUserId = id;
            // Pre-fill modal fields
            document.getElementById('user-modal-title').textContent = 'Edit User';
            document.getElementById('user-username').value = user.username;
            document.getElementById('user-email').value = user.email;
            document.getElementById('user-password').value = '';
            document.getElementById('user-role').value = user.role;
            showUserModal();
        }
    }

    editTask(id) {
        const task = this.tasks.find(t => t.id === id);
        if (task) {
            this.editingTaskId = id;
            // Pre-fill modal fields for description editing
            document.getElementById('task-title').value = task.title;
            document.getElementById('task-title').disabled = true;
            document.getElementById('task-description').value = task.description;
            document.getElementById('task-assigned-to').value = task.assigned_to;
            document.getElementById('task-assigned-to').disabled = true;
            document.getElementById('task-deadline').value = task.deadline;
            document.getElementById('task-deadline').disabled = true;
            document.getElementById('task-modal-title').textContent = 'Edit Task Description';
            showTaskModal(task);
        }
    }

    async saveUser() {
        const id = this.editingUserId;
        const username = document.getElementById('user-username').value;
        const email = document.getElementById('user-email').value;
        const password = document.getElementById('user-password').value;
        const role = document.getElementById('user-role').value;
        const data = { username, email, role };
        if (id) {
            // Editing user: only send password if not empty
            data.id = id;
            if (password) data.password = password;
            await this.apiCall('/api/admin/users.php', 'PUT', data);
            this.showToast('User updated!', 'success');
        } else {
            // Creating user: password is required
            if (!password) {
                this.showToast('Password is required for new users.', 'error');
                return;
            }
            data.password = password;
            await this.apiCall('/api/admin/users.php', 'POST', data);
            this.showToast('User added!', 'success');
        }
        await this.loadUsers();
        hideUserModal();
        this.editingUserId = null;
    }

    async saveTask() {
        const id = this.editingTaskId;
        const description = document.getElementById('task-description').value;
        if (id) {
            // Editing: only update description
            try {
                await this.apiCall('/api/admin/tasks.php', 'PUT', { id, description });
                this.showToast('Task description updated!', 'success');
                await this.loadAllTasks();
                hideTaskModal();
            } catch (error) {}
        } else {
            // Creating new task
            const title = document.getElementById('task-title').value;
            const assigned_to = document.getElementById('task-assigned-to').value;
            const deadline = document.getElementById('task-deadline').value;
            if (!title || !assigned_to || !deadline) {
                this.showToast('Please fill in all required fields.', 'error');
                return;
            }
            try {
                await this.apiCall('/api/admin/tasks.php', 'POST', {
                    title,
                    description,
                    assigned_to,
                    deadline
                });
                this.showToast('Task added!', 'success');
                await this.loadAllTasks();
                hideTaskModal();
            } catch (error) {}
        }
    }

    async deleteUser(id) {
        this.userIdToDelete = id;
        showDeleteUserModal();
    }

    async deleteUserConfirmed(id) {
        try {
            await this.apiCall('/api/admin/users.php', 'DELETE', { id });
            this.showToast('User deleted!', 'success');
            await this.loadUsers();
            hideDeleteUserModal(); // Hide modal only after successful delete
        } catch (error) {}
    }

    async loadTaskComments(taskId) {
        const endpoint = this.currentUser.role === 'admin'
            ? `/api/admin/task_comments.php?task_id=${taskId}`
            : `/api/user/task_comments.php?task_id=${taskId}`;
        try {
            const result = await this.apiCall(endpoint, 'GET', null, true);
            this.renderTaskComments(result.comments || []);
        } catch (error) {
            this.renderTaskComments([]);
        }
    }

    // In updateTaskStatus, do not reload user tasks for users (SSE will handle it)
    async updateTaskStatus(taskId, status) {
        try {
            await this.apiCall('/api/user/tasks.php', 'PUT', { id: taskId, status });
            this.showToast('Task status updated!', 'success');
            await this.loadDashboardStats();
            if (this.currentUser && this.currentUser.role === 'admin') {
                await this.loadAllTasks();
            }
        } catch (error) {}
    }
}

window.app = new TaskManager();

// Add event listeners for both comment forms after rendering
window.addEventListener('click', function(e) {
    if (e.target && e.target.id === 'task-modal' && window.app) {
        window.app.removeCommentsSection();
    }
});
window.addEventListener('DOMContentLoaded', () => {
    // Add event listeners for comment forms after rendering
    document.addEventListener('submit', function(e) {
        if (e.target && e.target.id === 'user-comment-form') {
            e.preventDefault();
            const input = document.getElementById('user-comment-input');
            if (input && input.value.trim() && window.app && window.app.editingTaskId) {
                window.app.addTaskComment(window.app.editingTaskId, input.value.trim());
                input.value = '';
            }
        }
        if (e.target && e.target.id === 'admin-comment-form') {
            e.preventDefault();
            const input = document.getElementById('admin-comment-input');
            if (input && input.value.trim() && window.app && window.app.editingTaskId) {
                window.app.addTaskComment(window.app.editingTaskId, input.value.trim());
                input.value = '';
            }
        }
    });
});