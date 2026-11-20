/* URIMS · Shared interface behaviours */
(function () {
    'use strict';

    /* ---------------------------------------------------------------
     | Dropdown menus
     --------------------------------------------------------------- */
    var dropdowns = Array.prototype.slice.call(document.querySelectorAll('[data-dropdown]'));

    function closeDropdown(dropdown, returnFocus) {
        var toggle = dropdown.querySelector('[data-dropdown-toggle]');
        var menu = dropdown.querySelector('.dropdown__menu');
        if (!menu || menu.hidden) {
            return;
        }
        menu.hidden = true;
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
            if (returnFocus) {
                toggle.focus({ preventScroll: true });
            }
        }
    }

    function closeAllDropdowns(except) {
        dropdowns.forEach(function (dropdown) {
            if (dropdown !== except) {
                closeDropdown(dropdown, false);
            }
        });
    }

    dropdowns.forEach(function (dropdown) {
        var toggle = dropdown.querySelector('[data-dropdown-toggle]');
        var menu = dropdown.querySelector('.dropdown__menu');
        if (!toggle || !menu) {
            return;
        }

        toggle.addEventListener('click', function (event) {
            event.stopPropagation();
            var willOpen = menu.hidden;
            closeAllDropdowns(dropdown);
            menu.hidden = !willOpen;
            toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            if (willOpen) {
                var firstItem = menu.querySelector('.dropdown__item');
                if (firstItem) {
                    firstItem.focus({ preventScroll: true });
                }
            }
        });

        dropdown.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeDropdown(dropdown, true);
            }
        });
    });

    document.addEventListener('click', function (event) {
        if (!event.target.closest('[data-dropdown]')) {
            closeAllDropdowns(null);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeAllDropdowns(null);
        }
    });

    /* ---------------------------------------------------------------
     | Toasts
     --------------------------------------------------------------- */
    function dismissToast(toast) {
        if (!toast || toast.classList.contains('is-leaving')) {
            return;
        }
        toast.classList.add('is-leaving');
        window.setTimeout(function () {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 180);
    }

    var toasts = Array.prototype.slice.call(document.querySelectorAll('.toast'));
    toasts.forEach(function (toast) {
        var close = toast.querySelector('[data-toast-close]');
        if (close) {
            close.addEventListener('click', function () {
                dismissToast(toast);
            });
        }
        window.setTimeout(function () {
            dismissToast(toast);
        }, 7000);
    });

    /* ---------------------------------------------------------------
     | Password visibility
     --------------------------------------------------------------- */
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-toggle-password]');
        if (!button) {
            return;
        }
        var input = document.getElementById(button.getAttribute('data-toggle-password'));
        if (!input) {
            return;
        }
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        button.setAttribute('aria-pressed', show ? 'true' : 'false');
    });

    /* ---------------------------------------------------------------
     | Delete confirmation modal
     --------------------------------------------------------------- */
    var overlay = document.querySelector('[data-confirm-overlay]');
    var pendingForm = null;
    var lastTrigger = null;
    var confirmTitle = overlay ? overlay.querySelector('#confirm-title') : null;
    var confirmMessage = overlay ? overlay.querySelector('#confirm-message') : null;
    var confirmAccept = overlay ? overlay.querySelector('[data-confirm-accept]') : null;
    var confirmCancel = overlay ? overlay.querySelector('[data-confirm-cancel]') : null;

    function closeConfirm() {
        if (!overlay) {
            return;
        }
        overlay.hidden = true;
        document.body.style.removeProperty('overflow');
        if (lastTrigger) {
            lastTrigger.focus({ preventScroll: true });
        }
        pendingForm = null;
    }

    function openConfirm(trigger) {
        if (!overlay) {
            return;
        }
        pendingForm = document.getElementById(trigger.getAttribute('data-form') || '');
        lastTrigger = trigger;
        if (confirmTitle && trigger.getAttribute('data-confirm-title')) {
            confirmTitle.textContent = trigger.getAttribute('data-confirm-title');
        }
        if (confirmMessage && trigger.getAttribute('data-confirm-message')) {
            confirmMessage.textContent = trigger.getAttribute('data-confirm-message');
        }
        overlay.hidden = false;
        document.body.style.overflow = 'hidden';
        if (confirmCancel) {
            confirmCancel.focus({ preventScroll: true });
        }
    }

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-confirm-delete]');
        if (!trigger) {
            return;
        }
        event.preventDefault();
        openConfirm(trigger);
    });

    if (confirmAccept) {
        confirmAccept.addEventListener('click', function () {
            if (pendingForm) {
                confirmAccept.disabled = true;
                confirmAccept.textContent = 'Deleting…';
                pendingForm.submit();
            }
        });
    }

    if (confirmCancel) {
        confirmCancel.addEventListener('click', closeConfirm);
    }

    if (overlay) {
        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) {
                closeConfirm();
            }
        });
        overlay.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeConfirm();
            }
            if (event.key === 'Tab') {
                var focusable = overlay.querySelectorAll('button, [href], input, select, textarea');
                if (!focusable.length) {
                    return;
                }
                var first = focusable[0];
                var last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        });
    }

    /* ---------------------------------------------------------------
     | Auto-total for scored forms
     --------------------------------------------------------------- */
    var scoredForms = document.querySelectorAll('form');
    scoredForms.forEach(function (form) {
        var totalField = form.querySelector('[data-computed-total]');
        if (!totalField) {
            return;
        }
        var parts = form.querySelectorAll('input[type="number"]:not([readonly]):not([data-computed-total])');
        function recalculate() {
            var total = 0;
            parts.forEach(function (input) {
                total += parseFloat(input.value || '0') || 0;
            });
            totalField.value = total;
        }
        parts.forEach(function (input) {
            input.addEventListener('input', recalculate);
        });
        recalculate();
    });

    /* ---------------------------------------------------------------
     | Client-side form validation and submit feedback
     --------------------------------------------------------------- */
    function fieldError(field, message) {
        var existing = field.parentNode.querySelector('.field-error');
        field.classList.add('is-invalid');
        field.setAttribute('aria-invalid', 'true');
        if (!existing) {
            var note = document.createElement('p');
            note.className = 'field-error';
            note.setAttribute('role', 'alert');
            note.textContent = message;
            field.parentNode.appendChild(note);
        }
    }

    function clearFieldError(field) {
        field.classList.remove('is-invalid');
        field.removeAttribute('aria-invalid');
        var wrapper = field.closest('.form-group') || field.parentNode;
        var note = wrapper.querySelector('.field-error');
        if (note) {
            note.remove();
        }
    }

    document.querySelectorAll('form').forEach(function (form) {
        if (form.hasAttribute('novalidate')) {
            form.addEventListener('submit', function (event) {
                var firstInvalid = null;
                form.querySelectorAll('.is-invalid').forEach(clearFieldError);
                form.querySelectorAll('[required]').forEach(function (field) {
                    if (field.type === 'file') {
                        return;
                    }
                    if (!field.value.trim()) {
                        fieldError(field, 'This field is required.');
                        if (!firstInvalid) {
                            firstInvalid = field;
                        }
                    } else if (field.type === 'email' && field.value.indexOf('@') === -1) {
                        fieldError(field, 'Enter a valid email address.');
                        if (!firstInvalid) {
                            firstInvalid = field;
                        }
                    }
                });
                if (firstInvalid) {
                    event.preventDefault();
                    firstInvalid.focus();
                    firstInvalid.scrollIntoView({ block: 'center' });
                    return;
                }
                if (form.dataset.submitting === '1') {
                    event.preventDefault();
                    return;
                }
                form.dataset.submitting = '1';
                var submit = form.querySelector('button[type="submit"]');
                if (submit && !form.hasAttribute('data-no-loading')) {
                    submit.classList.add('is-loading');
                    submit.setAttribute('aria-disabled', 'true');
                    if (!submit.querySelector('.spinner')) {
                        var spinner = document.createElement('span');
                        spinner.className = 'spinner';
                        spinner.setAttribute('aria-hidden', 'true');
                        submit.appendChild(spinner);
                    }
                }
            });
        }

        form.addEventListener('input', function (event) {
            if (event.target.classList && event.target.classList.contains('is-invalid')) {
                clearFieldError(event.target);
            }
        });
    });
})();
