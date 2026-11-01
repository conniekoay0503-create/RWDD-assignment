/*
 * main.js - shared JavaScript for every page.
 * Plain JavaScript (no libraries). Each part looks for an HTML data- attribute,
 * so a page only gets a feature if its HTML asks for it.
 */

/* ============ 1. Saved preferences (dark mode + text size) ============ */
// localStorage keeps a small value in this browser. It can be blocked (e.g. private mode),
// so every read/write is wrapped in try...catch and the page still works without it.
function loadPref(key, fallback) {
    try {
        var value = localStorage.getItem(key);
        return value === null ? fallback : value;
    } catch (e) {
        return fallback;
    }
}
function savePref(key, value) {
    try { localStorage.setItem(key, value); } catch (e) { /* ignore */ }
}

var TEXT_SIZES = [90, 100, 110, 125];   // allowed text sizes in %

function applyPrefs() {
    var theme = loadPref('theme', 'light');
    var size = parseInt(loadPref('textSize', '100'), 10);
    if (TEXT_SIZES.indexOf(size) === -1) size = 100;

    document.documentElement.setAttribute('data-theme', theme);
    document.documentElement.style.fontSize = size + '%';   // every size in the CSS uses rem, so everything scales

    document.querySelectorAll('[data-text-size-label]').forEach(function (label) {
        label.textContent = size + '%';
    });
    document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
        btn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');   // CSS swaps the moon / sun icon
    });
}
applyPrefs();

document.addEventListener('click', function (event) {
    var themeBtn = event.target.closest('[data-theme-toggle]');
    if (themeBtn) {
        savePref('theme', loadPref('theme', 'light') === 'dark' ? 'light' : 'dark');
        applyPrefs();
    }

    var sizeBtn = event.target.closest('[data-text-size]');
    if (sizeBtn) {
        var current = TEXT_SIZES.indexOf(parseInt(loadPref('textSize', '100'), 10));
        if (current === -1) current = 1;
        var next = current + parseInt(sizeBtn.getAttribute('data-text-size'), 10);
        next = Math.max(0, Math.min(TEXT_SIZES.length - 1, next));   // stay inside the list
        savePref('textSize', TEXT_SIZES[next]);
        applyPrefs();
    }

    if (event.target.closest('[data-reset-prefs]')) {
        savePref('theme', 'light');
        savePref('textSize', '100');
        applyPrefs();
    }
});

/* ============ 2. Dropdowns (notification bell, account menu) ============ */
// <button data-dropdown="panelId"> opens / closes the panel with that id.
function closeDropdowns(except) {
    document.querySelectorAll('[data-dropdown]').forEach(function (btn) {
        var panel = document.getElementById(btn.getAttribute('data-dropdown'));
        if (panel === except) return;
        panel.hidden = true;
        btn.setAttribute('aria-expanded', 'false');
    });
}
document.addEventListener('click', function (event) {
    var btn = event.target.closest('[data-dropdown]');
    if (btn) {
        var panel = document.getElementById(btn.getAttribute('data-dropdown'));
        closeDropdowns(panel);                 // only one dropdown open at a time
        panel.hidden = !panel.hidden;
        btn.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
        return;
    }
    // Clicking anywhere outside an open panel closes it
    if (!event.target.closest('.dropdown-panel')) closeDropdowns(null);
});
document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeDropdowns(null);
});

/* ============ 3. Show / hide password ============ */
document.addEventListener('click', function (event) {
    var btn = event.target.closest('[data-toggle-password]');
    if (!btn) return;
    var input = document.getElementById(btn.getAttribute('data-toggle-password'));
    var hidden = input.type === 'password';
    input.type = hidden ? 'text' : 'password';
    btn.textContent = hidden ? 'Hide' : 'Show';
});

/* ============ 4. Confirm before a link is followed ============ */
document.addEventListener('click', function (event) {
    var link = event.target.closest('a[data-confirm]');
    if (link && !confirm(link.getAttribute('data-confirm'))) {
        event.preventDefault();
    }
});

/* ============ 5. Form validation (forms with data-validate) ============ */
// The server (PHP) always checks again - this only gives faster feedback.
function fieldError(input) {
    var value = input.value.trim();
    var label = input.labels && input.labels[0] ? input.labels[0].firstChild.textContent.trim() : 'This field';

    if (input.type === 'checkbox') {
        return input.required && !input.checked ? 'Please tick this box to continue.' : '';
    }
    if (input.required && value === '') return label + ' is required.';
    if (value === '') return '';
    if (input.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return 'Please enter a valid email address.';
    if (input.minLength > 0 && value.length < input.minLength) return label + ' must be at least ' + input.minLength + ' characters.';
    if (input.pattern && !new RegExp('^(?:' + input.pattern + ')$').test(value)) {
        return input.getAttribute('data-error') || 'Please match the requested format.';
    }
    if (input.type === 'number') {
        var n = Number(value);
        if (isNaN(n)) return label + ' must be a number.';
        if (input.min !== '' && n < Number(input.min)) return label + ' must be at least ' + input.min + '.';
        if (input.max !== '' && n > Number(input.max)) return label + ' must be at most ' + input.max + '.';
    }
    var matchId = input.getAttribute('data-match');
    if (matchId && value !== document.getElementById(matchId).value) {
        return input.getAttribute('data-error') || 'The values do not match.';
    }
    return '';
}

function showFieldError(input, message) {
    var holder = input.closest('.field') || input.closest('.check') || input.parentNode;
    var box = holder.querySelector('.field-error');
    if (!box) {
        box = document.createElement('small');
        box.className = 'field-error';
        holder.appendChild(box);
    }
    box.textContent = message;
    holder.classList.toggle('has-error', message !== '');
    input.setAttribute('aria-invalid', message ? 'true' : 'false');
}

function validateForm(form) {
    var firstBad = null;
    form.querySelectorAll('input, select, textarea').forEach(function (input) {
        if (input.type === 'hidden' || input.disabled) return;
        var message = fieldError(input);
        showFieldError(input, message);
        if (message && !firstBad) firstBad = input;
    });
    if (firstBad) firstBad.focus();
    return firstBad === null;
}

// Check one field as soon as the user leaves it
document.addEventListener('focusout', function (event) {
    var form = event.target.form;
    if (form && form.hasAttribute('data-validate') && event.target.matches('input, select, textarea')) {
        showFieldError(event.target, fieldError(event.target));
    }
});

// On submit: validate first, then ask to confirm (data-confirm on the form)
document.addEventListener('submit', function (event) {
    var form = event.target;
    if (form.hasAttribute('data-validate') && !validateForm(form)) {
        event.preventDefault();
        return;
    }
    if (form.hasAttribute('data-confirm') && !confirm(form.getAttribute('data-confirm'))) {
        event.preventDefault();
    }
});

/* ============ 6. Password strength meter ============ */
function passwordScore(pw) {
    var score = 0;
    if (pw.length >= 8) score++;
    if (pw.length >= 12) score++;
    if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;
    return score;   // 0 to 5
}
document.addEventListener('input', function (event) {
    var input = event.target;
    if (!input.hasAttribute('data-strength')) return;
    var field = input.closest('.field');
    var bar = field.querySelector('.strength span');
    var text = field.querySelector('.strength-text');
    var score = input.value === '' ? 0 : passwordScore(input.value);
    var words = ['Too short', 'Weak', 'Fair', 'Good', 'Strong', 'Very strong'];
    var level = input.value.length < 8 ? 'weak' : score <= 2 ? 'fair' : 'strong';

    bar.style.width = (score / 5 * 100) + '%';
    bar.className = 'level-' + level;
    if (text) text.textContent = input.value === '' ? 'At least 8 characters' : (input.value.length < 8 ? words[0] : words[score]);
});

/* ============ 7. Search filters ============ */
// 7a. Table: text search + role dropdown (data-filter-table / data-filter-role)
function filterTable(tableId) {
    var table = document.getElementById(tableId);
    var search = document.querySelector('[data-filter-table="' + tableId + '"]');
    var roleSelect = document.querySelector('[data-filter-role="' + tableId + '"]');
    var text = search ? search.value.trim().toLowerCase() : '';
    var role = roleSelect ? roleSelect.value : '';
    var shown = 0;

    table.querySelectorAll('tbody tr').forEach(function (row) {
        var matchText = row.textContent.toLowerCase().indexOf(text) !== -1;
        var matchRole = role === '' || row.getAttribute('data-role') === role;
        row.hidden = !(matchText && matchRole);
        if (!row.hidden) shown++;
    });
    var empty = document.querySelector('[data-empty-for="' + tableId + '"]');
    if (empty) empty.hidden = shown > 0;
}

// 7b. List: text search (data-filter-list)
function filterList(listId, text) {
    var list = document.getElementById(listId);
    list.querySelectorAll('li').forEach(function (item) {
        item.classList.toggle('filtered-out', item.textContent.toLowerCase().indexOf(text) === -1);
    });
    applyShowLimit(list);
}

document.addEventListener('input', function (event) {
    var el = event.target;
    if (el.hasAttribute('data-filter-table')) filterTable(el.getAttribute('data-filter-table'));
    if (el.hasAttribute('data-filter-list')) filterList(el.getAttribute('data-filter-list'), el.value.trim().toLowerCase());
});
document.addEventListener('change', function (event) {
    if (event.target.hasAttribute('data-filter-role')) filterTable(event.target.getAttribute('data-filter-role'));
});

/* ============ 8. Show more / show less ============ */
// A list with data-show-limit="5" shows 5 items; "Show more" adds 5 more each click.
function applyShowLimit(list) {
    var limit = parseInt(list.getAttribute('data-showing') || list.getAttribute('data-show-limit'), 10);
    var visible = list.querySelectorAll('li:not(.filtered-out)');
    var count = 0;
    list.querySelectorAll('li').forEach(function (item) {
        if (item.classList.contains('filtered-out')) {
            item.hidden = true;
        } else {
            count++;
            item.hidden = count > limit;
        }
    });
    var more = document.querySelector('[data-show-more="' + list.id + '"]');
    var less = document.querySelector('[data-show-less="' + list.id + '"]');
    var step = parseInt(list.getAttribute('data-show-limit'), 10);
    if (more) more.hidden = visible.length <= limit;
    if (less) less.hidden = limit <= step;
}

document.querySelectorAll('[data-show-limit]').forEach(applyShowLimit);

document.addEventListener('click', function (event) {
    var more = event.target.closest('[data-show-more]');
    var less = event.target.closest('[data-show-less]');
    var btn = more || less;
    if (!btn) return;
    var list = document.getElementById(btn.getAttribute(more ? 'data-show-more' : 'data-show-less'));
    var step = parseInt(list.getAttribute('data-show-limit'), 10);
    var now = parseInt(list.getAttribute('data-showing') || step, 10);
    list.setAttribute('data-showing', more ? now + step : step);
    applyShowLimit(list);
});

/* ============ 9. Points calculator (Categories > Points calculator) ============ */
function updateCalculator() {
    var box = document.querySelector('[data-calculator]');
    if (!box) return;
    var option = box.querySelector('[data-calc-category]').selectedOptions[0];
    if (!option) return;
    var multiplier = parseFloat(option.getAttribute('data-multiplier'));
    var max = parseInt(option.getAttribute('data-max'), 10);
    var weight = parseFloat(box.querySelector('[data-calc-weight]').value) || 0;

    // Same rule as the server: MIN(weight x multiplier (x2 if repaired), max), rounded down
    var repaired = Math.min(Math.floor(weight * multiplier * 2), max);
    var recycled = Math.min(Math.floor(weight * multiplier), max);

    box.querySelector('[data-calc-out="repaired"]').textContent = repaired;
    box.querySelector('[data-calc-out="recycled"]').textContent = recycled;
    box.querySelector('[data-calc-out="volunteer"]').textContent = recycled;
    box.querySelector('[data-calc-rule]').textContent =
        weight + ' kg × ' + multiplier + ' = ' + Math.floor(weight * multiplier) + ' (max ' + max + ')';
    box.querySelector('[data-calc-note]').textContent =
        (weight * multiplier * 2 > max) ? 'The repaired amount has reached the max of ' + max + ' points.' : '';
}
document.addEventListener('input', function (event) {
    var box = event.target.closest('[data-calculator]');
    if (!box) return;
    // Keep the number box and the slider showing the same weight
    if (event.target.hasAttribute('data-calc-slider')) box.querySelector('[data-calc-weight]').value = event.target.value;
    if (event.target.hasAttribute('data-calc-weight')) box.querySelector('[data-calc-slider]').value = event.target.value;
    updateCalculator();
});
document.addEventListener('change', function (event) {
    if (event.target.closest('[data-calculator]')) updateCalculator();
});
updateCalculator();

/* ============ 10. Tabs (Profile page) ============ */
function openTab(name) {
    var tabs = document.querySelector('[data-tabs]');
    if (!tabs || !document.getElementById(name)) return;
    tabs.querySelectorAll('[data-tab]').forEach(function (btn) {
        var on = btn.getAttribute('data-tab') === name;
        btn.classList.toggle('active', on);
        btn.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    tabs.querySelectorAll('.tab-panel').forEach(function (panel) {
        panel.hidden = panel.id !== name;
    });
}
if (document.querySelector('[data-tabs]')) {
    // Open the tab in the address (#password) - used after a form is saved
    var firstTab = document.querySelector('[data-tab]').getAttribute('data-tab');
    openTab(location.hash ? location.hash.substring(1) : firstTab);
    if (!document.querySelector('[data-tab].active')) openTab(firstTab);
}
document.addEventListener('click', function (event) {
    var btn = event.target.closest('[data-tab]');
    if (!btn) return;
    openTab(btn.getAttribute('data-tab'));
    history.replaceState(null, '', '#' + btn.getAttribute('data-tab'));
});

/* ============ 11. Small helpers ============ */
// Print button (Reports page)
document.addEventListener('click', function (event) {
    if (event.target.closest('[data-print]')) window.print();
});

// End date can't be before start date: <input data-start-for="end_date">
document.addEventListener('change', function (event) {
    var id = event.target.getAttribute('data-start-for');
    if (!id) return;
    var endInput = document.getElementById(id);
    endInput.min = event.target.value;
    if (endInput.value && endInput.value < event.target.value) endInput.value = event.target.value;
});

// Character counter: <small data-count-for="textareaId">
document.querySelectorAll('[data-count-for]').forEach(function (counter) {
    var box = document.getElementById(counter.getAttribute('data-count-for'));
    function update() { counter.textContent = '(' + box.value.length + ' / ' + box.maxLength + ')'; }
    box.addEventListener('input', update);
    update();
});

// Reports: only show the inputs for the chosen period
var reportForm = document.querySelector('[data-report-form]');
if (reportForm) {
    var updatePeriod = function () {
        var chosen = reportForm.querySelector('input[name="period"]:checked').value;
        reportForm.querySelectorAll('[data-period-field]').forEach(function (field) {
            field.hidden = field.getAttribute('data-period-field') !== chosen;
        });
    };
    reportForm.addEventListener('change', updatePeriod);
    updatePeriod();
}
