document.addEventListener("DOMContentLoaded", function () {
    // Global Theme Toggle Functionality (Desktop, Mobile & Auth Pages)
    function applyTheme(newTheme, syncBackend = true) {
        const normalized = newTheme === "dark" ? "dark" : "light";

        // 1. Update DOM attributes immediately
        document.documentElement.setAttribute("data-theme", normalized);
        if (document.body) {
            document.body.setAttribute("data-theme", normalized);
        }

        // 2. Persist in LocalStorage
        try {
            localStorage.setItem("expense_tracker_theme", normalized);
        } catch (err) {}

        // 3. Persist in cookie so PHP knows on next request / navigation
        try {
            document.cookie = "expense_tracker_theme=" + encodeURIComponent(normalized) + "; path=/; max-age=31536000; SameSite=Lax";
        } catch (err) {}

        // 4. Sync dropdown on profile/preferences.php if present
        const themeSelect = document.getElementById("theme");
        if (themeSelect && themeSelect.value !== normalized) {
            themeSelect.value = normalized;
        }

        // 5. Send update to backend to save in database and session (only if user is logged in)
        if (syncBackend && window.isLoggedIn) {
            const navbar = document.querySelector(".navbar");
            const csrfInput = document.querySelector("input[name='csrf_token']");
            const csrfToken = navbar ? navbar.getAttribute("data-csrf-token") : (csrfInput ? csrfInput.value : "");

            if (csrfToken) {
                const formData = new FormData();
                formData.append("theme", normalized);
                formData.append("csrf_token", csrfToken);

                fetch("/daily-expense-tracker-v2/profile/ajax_theme.php", {
                    method: "POST",
                    headers: {
                        "X-Requested-With": "XMLHttpRequest"
                    },
                    body: formData
                }).then(function (res) {
                    if (!res.ok) {
                        throw new Error("HTTP " + res.status);
                    }
                    return res.json();
                }).then(function (data) {
                    if (data && data.success && data.theme) {
                        window.serverTheme = data.theme;
                        document.documentElement.setAttribute("data-theme", data.theme);
                        try {
                            localStorage.setItem("expense_tracker_theme", data.theme);
                        } catch (e) {}
                    }
                }).catch(function (err) {
                    console.warn("Could not save theme preference asynchronously:", err);
                });
            }
        }
    }

    function toggleTheme() {
        const currentTheme = document.documentElement.getAttribute("data-theme") || "light";
        const newTheme = currentTheme === "dark" ? "light" : "dark";
        applyTheme(newTheme, true);
    }

    window.toggleTheme = toggleTheme;
    window.applyTheme = applyTheme;

    // Delegated click handler so clicking anywhere on the sun/moon button (including SVG or path) triggers theme toggle
    document.addEventListener("click", function (e) {
        const themeBtn = e.target.closest(".theme-toggle-btn");
        if (themeBtn) {
            e.preventDefault();
            e.stopPropagation();
            toggleTheme();
        }
    });

    // If user changes the theme <select> on preferences.php, apply theme live
    const themeDropdown = document.getElementById("theme");
    if (themeDropdown) {
        themeDropdown.addEventListener("change", function () {
            applyTheme(this.value, false);
        });
    }

    // Mobile Navbar Toggle
    const menuToggle = document.getElementById("menu-toggle");
    const navLinks = document.getElementById("nav-links");

    if (menuToggle && navLinks) {
        menuToggle.addEventListener("click", function () {
            navLinks.classList.toggle("active");
            const isOpen = navLinks.classList.contains("active");
            menuToggle.setAttribute("aria-expanded", isOpen);
        });
    }

    // Quick Expense Modal
    const openExpenseModal = document.getElementById("open-expense-modal");
    const expenseModal = document.getElementById("expense-modal");
    const closeExpenseModal = document.getElementById("close-expense-modal");
    const cancelExpenseModal = document.getElementById("cancel-expense-modal");

    function openExpenseModalWindow() {
        if (!expenseModal) {
            return;
        }

        expenseModal.classList.add("active");
        expenseModal.setAttribute("aria-hidden", "false");

        const amountInput = document.getElementById("quick-amount");
        if (amountInput) {
            amountInput.focus();
        }
    }

    function closeExpenseModalWindow() {
        if (!expenseModal) {
            return;
        }

        expenseModal.classList.remove("active");
        expenseModal.setAttribute("aria-hidden", "true");
    }

    if (openExpenseModal) {
        openExpenseModal.addEventListener("click", openExpenseModalWindow);
    }

    if (closeExpenseModal) {
        closeExpenseModal.addEventListener("click", closeExpenseModalWindow);
    }

    if (cancelExpenseModal) {
        cancelExpenseModal.addEventListener("click", closeExpenseModalWindow);
    }

    if (expenseModal) {
        expenseModal.addEventListener("click", function (event) {
            if (event.target === expenseModal) {
                closeExpenseModalWindow();
            }
        });
    }

    document.addEventListener("keydown", function (event) {
        if (
            event.key === "Escape"
            && expenseModal
            && expenseModal.classList.contains("active")
        ) {
            closeExpenseModalWindow();
        }
    });

    // Auto-dismiss alert notifications after 3 seconds with smooth fade-out
    function initAutoDismissAlerts() {
        const alerts = document.querySelectorAll(".alert:not([data-persistent])");
        alerts.forEach(function (alert) {
            setTimeout(function () {
                alert.style.transition = "opacity 0.4s ease, transform 0.4s ease, max-height 0.4s ease, margin-bottom 0.4s ease, padding 0.4s ease";
                alert.style.opacity = "0";
                alert.style.transform = "translateY(-6px)";
                setTimeout(function () {
                    alert.style.maxHeight = "0";
                    alert.style.paddingTop = "0";
                    alert.style.paddingBottom = "0";
                    alert.style.marginBottom = "0";
                    alert.style.borderWidth = "0";
                    setTimeout(function () {
                        if (alert.parentNode) {
                            alert.parentNode.removeChild(alert);
                        }
                    }, 400);
                }, 100);
            }, 3000);
        });
    }

    initAutoDismissAlerts();

    // Auto-remove success message URL parameter if present
    const expenseSuccessMessage = document.getElementById("expense-success-message");
    if (expenseSuccessMessage) {
        setTimeout(function () {
            const url = new URL(window.location.href);
            if (url.searchParams.has("expense_added")) {
                url.searchParams.delete("expense_added");
                window.history.replaceState({}, document.title, url.pathname + url.search);
            }
        }, 3000);
    }

    // Custom Downward Select Dropdowns (solves native OS popup width & upward alignment)
    function initCustomSelects() {
        const selects = document.querySelectorAll("select:not(.no-custom-select)");
        selects.forEach(function (select) {
            if (select.closest(".custom-select-wrapper")) {
                return; // already initialized
            }

            // Create container wrapper
            const wrapper = document.createElement("div");
            wrapper.className = "custom-select-wrapper";
            select.parentNode.insertBefore(wrapper, select);
            wrapper.appendChild(select);

            // Hide native select visually while keeping it fully functional in DOM
            select.classList.add("custom-select-native");

            // Create trigger button
            const trigger = document.createElement("button");
            trigger.type = "button";
            trigger.className = "custom-select-trigger";
            trigger.setAttribute("aria-haspopup", "listbox");
            trigger.setAttribute("aria-expanded", "false");

            const selectedOption = select.options[select.selectedIndex] || select.options[0];
            const label = document.createElement("span");
            label.className = "custom-select-label";
            label.textContent = selectedOption ? selectedOption.textContent.trim() : "";
            trigger.appendChild(label);

            // Arrow SVG
            const arrow = document.createElementNS("http://www.w3.org/2000/svg", "svg");
            arrow.setAttribute("class", "custom-select-arrow");
            arrow.setAttribute("width", "14");
            arrow.setAttribute("height", "14");
            arrow.setAttribute("viewBox", "0 0 24 24");
            arrow.setAttribute("fill", "none");
            arrow.setAttribute("stroke", "currentColor");
            arrow.setAttribute("stroke-width", "2");
            arrow.setAttribute("stroke-linecap", "round");
            arrow.setAttribute("stroke-linejoin", "round");
            const poly = document.createElementNS("http://www.w3.org/2000/svg", "polyline");
            poly.setAttribute("points", "6 9 12 15 18 9");
            arrow.appendChild(poly);
            trigger.appendChild(arrow);

            wrapper.appendChild(trigger);

            // Dropdown menu (strictly opens downward)
            const dropdown = document.createElement("div");
            dropdown.className = "custom-select-dropdown";
            dropdown.setAttribute("role", "listbox");

            function populateOptions() {
                dropdown.innerHTML = "";
                Array.from(select.options).forEach(function (opt, idx) {
                    const optEl = document.createElement("div");
                    optEl.className = "custom-select-option" + (opt.selected ? " is-selected" : "");
                    optEl.setAttribute("role", "option");
                    optEl.setAttribute("data-value", opt.value);
                    optEl.textContent = opt.textContent.trim();

                    optEl.addEventListener("click", function (e) {
                        e.stopPropagation();
                        select.selectedIndex = idx;
                        label.textContent = opt.textContent.trim();
                        dropdown.querySelectorAll(".custom-select-option").forEach(function (o) {
                            o.classList.remove("is-selected");
                        });
                        optEl.classList.add("is-selected");
                        closeDropdown();

                        // Dispatch change event on native select
                        select.dispatchEvent(new Event("change", { bubbles: true }));
                    });

                    dropdown.appendChild(optEl);
                });
            }

            populateOptions();
            wrapper.appendChild(dropdown);

            function openDropdown() {
                // Close any other open custom dropdowns first
                document.querySelectorAll(".custom-select-dropdown.is-open").forEach(function (d) {
                    d.classList.remove("is-open");
                    if (d.previousElementSibling) {
                        d.previousElementSibling.setAttribute("aria-expanded", "false");
                    }
                    const parentWrap = d.closest(".custom-select-wrapper");
                    if (parentWrap) parentWrap.classList.remove("is-active");
                });

                dropdown.classList.add("is-open");
                trigger.setAttribute("aria-expanded", "true");
                wrapper.classList.add("is-active");

                const currentSelected = dropdown.querySelector(".is-selected");
                if (currentSelected) {
                    currentSelected.scrollIntoView({ block: "nearest" });
                }
            }

            function closeDropdown() {
                dropdown.classList.remove("is-open");
                trigger.setAttribute("aria-expanded", "false");
                wrapper.classList.remove("is-active");
            }

            trigger.addEventListener("click", function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (dropdown.classList.contains("is-open")) {
                    closeDropdown();
                } else {
                    openDropdown();
                }
            });

            // Listen for native select change (if updated elsewhere)
            select.addEventListener("change", function () {
                const currentOpt = select.options[select.selectedIndex];
                if (currentOpt) {
                    label.textContent = currentOpt.textContent.trim();
                    dropdown.querySelectorAll(".custom-select-option").forEach(function (o, idx) {
                        o.classList.toggle("is-selected", idx === select.selectedIndex);
                    });
                }
            });
        });

        // Close on click outside
        document.addEventListener("click", function (e) {
            if (!e.target.closest(".custom-select-wrapper")) {
                document.querySelectorAll(".custom-select-dropdown.is-open").forEach(function (d) {
                    d.classList.remove("is-open");
                    if (d.previousElementSibling) {
                        d.previousElementSibling.setAttribute("aria-expanded", "false");
                    }
                    const wrap = d.closest(".custom-select-wrapper");
                    if (wrap) wrap.classList.remove("is-active");
                });
            }
        });

        // Close on Escape key
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape") {
                document.querySelectorAll(".custom-select-dropdown.is-open").forEach(function (d) {
                    d.classList.remove("is-open");
                    if (d.previousElementSibling) {
                        d.previousElementSibling.setAttribute("aria-expanded", "false");
                    }
                    const wrap = d.closest(".custom-select-wrapper");
                    if (wrap) wrap.classList.remove("is-active");
                });
            }
        });
    }

    initCustomSelects();

    // Enhanced Date Input: Clicking anywhere in a date field triggers native calendar picker
    document.addEventListener("click", function (e) {
        if (e.target && e.target.tagName === "INPUT" && e.target.type === "date") {
            if (typeof e.target.showPicker === "function") {
                try {
                    e.target.showPicker();
                } catch (err) {}
            }
        }
    });
});
