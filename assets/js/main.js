// main.js: shared JavaScript for every page.

/* === 1. Save preferences (dark mode + text size ===)
* localStorage keeps a small value in this browser. It can be blocked (e.g. private mode),
* so every read/write is wrapped in try-catch and the page still works without it
*/
/*
* USER PREFERENCE STORAGE: Functions for saving and retrieving user preferences using browser local storage.
*/
function loadPref(key, fallback){
    try{ // Attempt to retrieve the saved preference
        var value = localStorage.getItem(key);
        return value === null ? fallback : value; //Strict Equality Operator: Compare values and data types without type conversion
    } catch (e) {
        return fallback;        
    }
}

// Save the preference to browser local storage
function savePref(key, value) {
    try {
        localStorage.setItem(key, value); 
    } catch (e) {
        // Ignore storage errors to prevent the application from crashing
    }
}

//allowed text sizes in %
var TEXT_SIZES = [90, 100, 110, 125];

// Apply the saved user preferences to the website
function applyPrefs(){
    // Retrieve the saved theme or use light mode by default
    var theme = loadPref('theme', 'light');
    // Retrieve the saved text size and convert it to a decimal integer
    var size = parseInt(loadPref('textSize', '100'), 10);
    // Reset to 100% if the saved text size is not supported
    if(TEXT_SIZES.indexOf(size) === -1) size = 100;

    // Apply the selected theme to the root HTML element
    document.documentElement.setAttribute('data-theme', theme);
    document.documentElement.style.fontSize = size + '%'; //every size in the CSS use rem, so everything scales

    document.querySelectorAll('[data-text-size-label]').forEach(function(label){
        label.textContent = size + '%';
    })
    document.querySelectorAll('[data-theme-toggle]').forEach(function(btn){
        btn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false'); // CSS swaps the moon / sun icon
    });
}
applyPrefs();

// Listen for click events across the entire document
document.addEventListener('click', function (event){
    var themeBtn = event.target.closest('[data-theme-toggle]');
    if (themeBtn){
        // Toggle between light mode and dark mode and save the new preference
        savePref('theme', loadPref('theme', 'light') === 'dark' ? 'light' : 'dark');
        applyPrefs();
    }

    var sizeBtn = event.target.closest('[data-text-size]');
    if(sizeBtn){
        // Find the current text size position in the available size list
        var current = TEXT_SIZES.indexOf(parseInt(loadPref('textSize', '100'), 10));
        // Use the default 100% text size if the current value is invalid
        if (current === -1) current = 1;
        var next = current + parseInt(sizeBtn.getAttribute('data-text-size'), 10);
        next = Math.max(0, Math.min(TEXT_SIZES.length - 1, next)); //stay inside the list
        savePref('textSize', TEXT_SIZES[next]);
        applyPrefs();
    }

    // Check whether the reset preferences button was clicked
    if(event.target.closest('[data-reset-prefs]')){
        savePref('theme', 'light');
        savePref('textSize', '100');
        applyPrefs();
    }
})

// === 2. Dropdown (notification bell, account menu) ===
// <button data-dropdown = "panelId"> opens / closes the panel with that id
function closeDropdowns(except) { // Close all dropdown panels except the specified panel
    // Select all buttons that control dropdown menus, loop through each dropdown button
    document.querySelectorAll('[data-dropdown]').forEach(function(btn) {
        // Find the dropdown panel linked to the current button
        var panel = document.getElementById(btn.getAttribute('data-dropdown'));
        // Skip the panel that should remain open
        if(panel === except) return;
        // Hide the current dropdown panel
        panel.hidden = true;
        // Update the button's accessibility state to indicate that the menu is closed
        btn.setAttribute('aria-expanded', 'false');
    });
}

// Listen for click events across the entire document
document.addEventListener('click', function (event) {
    var btn = event.target.closest('[data-dropdown]');
    if (btn) {
        // Find the dropdown panel associated with the clicked button
        var panel = document.getElementById(btn.getAttribute('data-dropdown'));
        closeDropdowns(panel); // Close all other dropdown menus and keep the selected panel unchanged
        // Toggle the dropdown panel between visible and hidden states
        panel.hidden = !panel.hidden;
        btn.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
        return;
    }
    // Clicking anywhere outside an open panel closes it
    if (!event.target.closest('.dropdown-panel')) closeDropdowns(null);
});
// Keyboard interaction: Allow users to close all dropdown menus by pressing the Esc key
document.addEventListener('keydown', function (event){
    // Check whether the Esc key was pressed
    if (event.key === 'Escape') {
        closeDropdowns(null);
    }
})

// ===3. Show / hide password ===
document.addEventListener('click', function (event) {
    // Find the nearest password visibility toggle button
    var btn = event.target.closest('[data-toggle-password]');
    // Stop execution if no password toggle button was clicked
    if (!btn) return;
    // Find the password input field linked to the clicked button
    var input = document.getElementById(btn.getAttribute('data-toggle-password'));
    // Check whether the password is currently hidden
    var hidden = input.type === 'password';
    // Toggle the password field between visible and hidden states
    input.type = hidden ? 'text' : 'password';
    btn.textContent = hidden ? 'Hide' : 'Show';
});

// ===4. Confirm before a link is followed (pop-up message) ===
document.addEventListener('click', function (event){
    var link = event.target.closest('a[data-confirm]');
    // Check whether the user cancels the confirmation dialog
    if (link && !confirm(link.getAttribute('data-confirm'))){
        // Prevent navigation if the user cancels the confirmation
        event.preventDefault();
    }
})

// === 5. Form validation (forms with data-validate) ===
// The server (PHP) always checks again - this only gives faster feedback
// Check user input and return an error message if the input is invalid.
function fieldError(input){ //// Validate a form field and return an error message if invalid
    // Get the input value and remove extra spaces
    var value = input.value.trim();
    // Get the field label or use a default name if no label exists
    var label = input.labels && input.labels [0] ? input.labels[0].firstChild.textContent.trim() : 'This field';

    if (input.type === 'checkbox') {
        return input.required && !input.checked ? 'Please tick this box to continue.' : '';
    }
    if (input.required && value === '') return label + ' is required.';
    if(value === '') return '';
    // Check whether the email address follows a valid basic format
    if (input.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return 'Please enter a valid email address.';
    if (input.minLength > 0 && value.length < input.minLength) return label + ' must be at least ' + input.minLength + ' characters.';
    // Validate the input against the specified pattern
    if (input.pattern && !new RegExp('^(?:' + input.pattern + ')$').test(value)){
        // Return a custom error message or the default message
        return input.getAttribute('data-error') || 'Please match the requested format.';
    }
    if (input.type === 'number'){
        var n = Number(value);
        // isNaN(n): is not a number (Check whether the entered value is a valid number)
        if (isNaN(n)) return label + ' must be a number.';
        // Check Minimum Length
        if (input.min !== '' && n < Number (input.min)) return label + ' must be at least ' + input.min + '.';
        if (input.max !== '' && n > Number(input.max)) return label + ' must be at most ' + input.max + '.';
    }
    // Check whether the input matches the value of the specified field
    var matchId = input.getAttribute ('data-match');
    if (matchId && value !== document.getElementById(matchId).value){
        return input.getAttribute('data-error') || 'The values do not match.';
    }
    return '';
}

// Display validation errors next to the corresponding form fields and update their visual and accessibility states.
function showFieldError(input, message){
    // Find the appropriate container for displaying the error message
    var holder = input.closest('.field') || input.closest('.check') || input.parentNode;
    // Look for an existing error message element inside the container
    var box = holder.querySelector('.field-error');
    // Create an error message element if one does not already exist
    if (!box) {
        box = document.createElement('small');
        box.className = 'field-error';
        holder.appendChild(box);
    }
    box.textContent = message;
    holder.classList.toggle ('has-error', message !== '');
    // Update the accessibility state to indicate whether the input is invalid
    input.setAttribute('aria-invalid', message ? 'true' : 'false');
}

// Validate all eligible fields within the specified form
function validateForm(form){
    var firstBad = null;
    // Select and loop through all input, select and textarea elements
    form.querySelectorAll('input, select, textarea').forEach(function(input){
        if(input.type === 'hidden' || input.disabled) return;
        var message = fieldError(input);
        showFieldError(input, message);
        if(message && !firstBad) firstBad = input;
    });
    if (firstBad) firstBad.focus();
    return firstBad === null;
}

// Validate individual form fields when users move away from them, without waiting for the entire form to be submitted.
//focusout: when user move to other input, will trigger this event
document.addEventListener('focusout', function (event) {
    // Get the form associated with the current input element
    var form = event.target.form;
    // Validate only form fields inside forms with the data-validate attribute
    if (form && form.hasAttribute('data-validate') && event.target.matches ('input, select, textarea')){
        // Validate the current field and display or clear its error message
        showFieldError(event.target, fieldError(event.target));
    }
});

// On submit: Validate the form first, then ask for confirmation before allowing submission (data-confirm on the form)
document.addEventListener('submit', function (event) {
    // Get the form that triggered the submission event
    var form = event.target;
    if (form.hasAttribute('data-validate') && !validateForm(form)) {
        // Prevent form submission when validation fails
        event.preventDefault();
        return;
    }
    // Ask the user to confirm before submitting the form
    if (form.hasAttribute('data-confirm') && !confirm(form.getAttribute('data-confirm'))) {
        event.preventDefault();
    }
});

/* ===6. Password strength meter === */
// Evaluate password strength by checking its length and character combinations, then return a score from 0 to 5.
function passwordScore(pw) {
    // Initialize the password strength score to zero
    var score = 0;
    // Check the password length
    if (pw.length >= 8) score++;
    if (pw.length >= 12) score++;
    // Check password contains both uppercase and lowercase letters
    if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) score++;
    // Check password contain number
    if (/[0-9]/.test(pw)) score++;
    // password contains at least one special character
    if (/[^A-Za-z0-9]/.test(pw)) score++;
    return score;   // 0 to 5
}
// Monitor password input in real time and update the strength indicator based on the password score.
document.addEventListener('input', function (event) { //Listen for input change across the entire document
    var input = event.target;
    // Skip inputs that do not have password strength checking enabled
    if (!input.hasAttribute('data-strength')) return;
    var field = input.closest('.field');
    var bar = field.querySelector('.strength span');
    // Find the text element used to display the password strength level
    var text = field.querySelector('.strength-text');
    // Calculate the password score or set it to zero if the input is empty
    var score = input.value === '' ? 0 : passwordScore(input.value);
    // Define the text labels for each password strength score
    var words = ['Too short', 'Weak', 'Fair', 'Good', 'Strong', 'Very strong'];
    var level = input.value.length < 8 ? 'weak' : score <= 2 ? 'fair' : 'strong';
    // Update the progress bar width based on the password score
    bar.style.width = (score / 5 * 100) + '%';
    bar.className = 'level-' + level;
    // Update the strength label based on the password length and score
    if (text) text.textContent = input.value === '' ? 'At least 8 characters' : (input.value.length < 8 ? words[0] : words[score]);
});

/* ==== 7. Search filters === */
// 7a. Table: text search + role dropdown (data-filter-table / data-filter-role)
function filterTable(tableId) {
    // Get the table element using its ID.
    var table = document.getElementById(tableId);
    // Find the search input associated with this table.
    var search = document.querySelector('[data-filter-table="' + tableId + '"]');
    // Find the role dropdown associated with this table.
    var roleSelect = document.querySelector('[data-filter-role="' + tableId + '"]');
    // Get the search text, remove extra spaces, and convert it to lowercase.
    // Use an empty string if the search input does not exist.
    var text = search ? search.value.trim().toLowerCase() : '';
    // Get the selected role from the dropdown.
    var role = roleSelect ? roleSelect.value : '';
    var shown = 0;

    // Loop through every row inside the table body.
    table.querySelectorAll('tbody tr').forEach(function (row) {
        // Check whether the row contains the search text.
        var matchText = row.textContent.toLowerCase().indexOf(text) !== -1;
        var matchRole = role === '' || row.getAttribute('data-role') === role;
        // Show the row only when both conditions are satisfied.
        row.hidden = !(matchText && matchRole);
        // Count the row if it is visible.
        if (!row.hidden) shown++;
    });
    // Find the empty message associated with this table.
    var empty = document.querySelector('[data-empty-for="' + tableId + '"]');
    if (empty) empty.hidden = shown > 0;
}

// 7b. List: text search (data-filter-list)
function filterList(listId, text) {
    var list = document.getElementById(listId);
    list.querySelectorAll('li').forEach(function (item) {
        // Add or remove the filtered-out class based on the search result.
        item.classList.toggle('filtered-out', item.textContent.toLowerCase().indexOf(text) === -1);
    });
    // Apply the display limit to the filtered list.
    applyShowLimit(list);
}

document.addEventListener('input', function (event) {
    // Get the HTML element that triggered the input event.
    var el = event.target;
    if (el.hasAttribute('data-filter-table')) filterTable(el.getAttribute('data-filter-table'));
    // Filter the associated list when the input belongs to a list search field.
    if (el.hasAttribute('data-filter-list')) filterList(el.getAttribute('data-filter-list'), el.value.trim().toLowerCase());
});
// Listen for changes to form control values across the document.
document.addEventListener('change', function (event) {
    if (event.target.hasAttribute('data-filter-role')) filterTable(event.target.getAttribute('data-filter-role'));
});

/* === 8. Show more / show less === */
// A list with data-show-limit="5" shows 5 items; "Show more" adds 5 more each click.
function applyShowLimit(list) {
    // Store the maximum number of items to display.
    var limit = parseInt(list.getAttribute('data-showing') || list.getAttribute('data-show-limit'), 10);
    // Get all list items that have not been excluded by the search filter.
    var visible = list.querySelectorAll('li:not(.filtered-out)');
    // Get all list items that have not been excluded by the search filter.
    var count = 0;
    list.querySelectorAll('li').forEach(function (item) {
        if (item.classList.contains('filtered-out')) {
            // Hide items that do not match the search filter.
            item.hidden = true;
        } else {
            count++;
            // Hide items that do not match the search filter.
            item.hidden = count > limit;
        }
    });
    // Find the Show More button associated with this list.
    var more = document.querySelector('[data-show-more="' + list.id + '"]');
    // Find the Show More button associated with this list.
    var less = document.querySelector('[data-show-less="' + list.id + '"]');
    // Get the number of items to add with each Show More click.
    var step = parseInt(list.getAttribute('data-show-limit'), 10);
    // Get the number of items to add with each Show More click.
    if (more) more.hidden = visible.length <= limit;
    if (less) less.hidden = limit <= step;
}

// Apply the initial display limit to every list that has a data-show-limit attribute.
document.querySelectorAll('[data-show-limit]').forEach(applyShowLimit);

document.addEventListener('click', function (event) {
    var more = event.target.closest('[data-show-more]');
    var less = event.target.closest('[data-show-less]');
    var btn = more || less;
    // Stop execution if the click did not come from a Show More or Show Less button.
    if (!btn) return;
    var list = document.getElementById(btn.getAttribute(more ? 'data-show-more' : 'data-show-less'));
    var step = parseInt(list.getAttribute('data-show-limit'), 10);
    var now = parseInt(list.getAttribute('data-showing') || step, 10);
    // Increase the display count or reset it to the initial limit.
    list.setAttribute('data-showing', more ? now + step : step);
    // Refresh the list using the updated display count.
    applyShowLimit(list);
});

/* === 9. Points calculator (Categories > Points calculator) === */
function updateCalculator() {
    var box = document.querySelector('[data-calculator]');
    // Stop execution if the calculator container does not exist.
    if (!box) return;
    // Get the currently selected category option.
    var option = box.querySelector('[data-calc-category]').selectedOptions[0];
    // Stop execution if no category option is selected.
    if (!option) return;
    // Get the category multiplier and convert it into a number.
    var multiplier = parseFloat(option.getAttribute('data-multiplier'));
    // Get the maximum allowed points as an integer.
    var max = parseInt(option.getAttribute('data-max'), 10);
    var weight = parseFloat(box.querySelector('[data-calc-weight]').value) || 0;

    // Apply the same calculation rule as the server.
    // Same rule as the server: MIN(weight x multiplier (x2 if repaired), max), rounded down
    var repaired = Math.min(Math.floor(weight * multiplier * 2), max);
    // Calculate recycled points without doubling the multiplier.
    var recycled = Math.min(Math.floor(weight * multiplier), max);

    // Display points
    box.querySelector('[data-calc-out="repaired"]').textContent = repaired;
    box.querySelector('[data-calc-out="recycled"]').textContent = recycled;
    box.querySelector('[data-calc-out="volunteer"]').textContent = recycled;
    box.querySelector('[data-calc-rule]').textContent =
        weight + ' kg × ' + multiplier + ' = ' + Math.floor(weight * multiplier) + ' (max ' + max + ')';
    // Display a warning if the raw repaired calculation exceeds the maximum.
    box.querySelector('[data-calc-note]').textContent =
        (weight * multiplier * 2 > max) ? 'The repaired amount has reached the max of ' + max + ' points.' : '';
}
document.addEventListener('input', function (event) {
    var box = event.target.closest('[data-calculator]');
    if (!box) return;
    if (event.target.hasAttribute('data-calc-slider')) box.querySelector('[data-calc-weight]').value = event.target.value;
    // Recalculate results when a form control inside a calculator changes.
    if (event.target.hasAttribute('data-calc-weight')) box.querySelector('[data-calc-slider]').value = event.target.value;
    updateCalculator();
});
document.addEventListener('change', function (event) {
    // Recalculate results when a form control inside a calculator changes.
    if (event.target.closest('[data-calculator]')) updateCalculator();
});
updateCalculator();

/* === 10. Tabs (Profile page) === */
function openTab(name) {
    // Find the main container that holds the tabs.
    var tabs = document.querySelector('[data-tabs]');
    // Stop if the tabs container or requested tab panel does not exist.
    if (!tabs || !document.getElementById(name)) return;
    // Find all tab buttons inside the tabs container.
    tabs.querySelectorAll('[data-tab]').forEach(function (btn) {
        // Check whether the current button belongs to the requested tab.
        var on = btn.getAttribute('data-tab') === name;
        // Add the active class to the selected tab and remove it from others.
        btn.classList.toggle('active', on);
        // Update the accessibility state of the tab button.
        btn.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    tabs.querySelectorAll('.tab-panel').forEach(function (panel) {
        panel.hidden = panel.id !== name;
    });
}
// Initialize the tab system only when a tabs container exists.
if (document.querySelector('[data-tabs]')) {
    // Open the tab in the address (#password) - used after a form is saved
    var firstTab = document.querySelector('[data-tab]').getAttribute('data-tab');
    // Open the tab specified in the URL hash, or use the first tab if no hash exists.
    openTab(location.hash ? location.hash.substring(1) : firstTab);
    // Open the first tab if no tab is currently active.
    if (!document.querySelector('[data-tab].active')) openTab(firstTab);
}
document.addEventListener('click', function (event) {
    // Find the nearest tab button from the clicked element.
    var btn = event.target.closest('[data-tab]');
    if (!btn) return;
    // Open the tab associated with the clicked button.
    openTab(btn.getAttribute('data-tab'));
    // Update the URL hash without reloading the page or adding a new history entry.
    history.replaceState(null, '', '#' + btn.getAttribute('data-tab'));
});

/* === 11. Small helpers === */
// Print button (Reports page)
document.addEventListener('click', function (event) {
    // Check whether the clicked element belongs to a print button.
    if (event.target.closest('[data-print]')) {
        // Open the browser print dialog.
        window.print();
    }
});

// Data validation
// End date can't be before start date: <input data-start-for="end_date">
document.addEventListener('change', function (event) {
    // Get the ID of the end date input linked to the changed start date.
    var id = event.target.getAttribute('data-start-for');
    // Stop if the input is not linked to an end date.
    if (!id) return;
    var endInput = document.getElementById(id);
    endInput.min = event.target.value;
    // Correct the end date if it is earlier than the start date.
    if (endInput.value && endInput.value < event.target.value) endInput.value = event.target.value;
});

// Character counter: <small data-count-for="textareaId">
document.querySelectorAll('[data-count-for]').forEach(function (counter) {
    // Find the textarea associated with the current counter.
    var box = document.getElementById(counter.getAttribute('data-count-for'));
    // Define a function to update the character counter.
    function update() { 
        // Display the current character count and maximum allowed length.
        counter.textContent = '(' + box.value.length + ' / ' + box.maxLength + ')'; 
    }
    // Update the character counter whenever the user types or deletes text.
    box.addEventListener('input', update);
    update();
});

// Reports: only show the inputs for the chosen period
var reportForm = document.querySelector('[data-report-form]');
if (reportForm) {
    // Define a function to update the visible report fields.
    var updatePeriod = function () {
        // Get the currently selected report period.
        var chosen = reportForm.querySelector('input[name="period"]:checked').value;
        reportForm.querySelectorAll('[data-period-field]').forEach(function (field) {
            // Show only the field that matches the selected period.
            field.hidden = field.getAttribute('data-period-field') !== chosen;
        });
    };
    // Update the visible fields whenever the report period changes.
    reportForm.addEventListener('change', updatePeriod);
    // Apply the initial report period selection.
    updatePeriod();
}
