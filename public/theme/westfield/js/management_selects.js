/* Dashboard-style menus backed by Moodle's original form selects and change handlers. */
(function() {
    'use strict';
    function init() {
        const root = document.getElementById('page-course-management');
        if (!root) {
            return;
        }
        const menus = [];
        let sequence = 0;
        function syncCategoryMove() {
            const destination = root.querySelector('select[name="movecategoriesto"]');
            const move = root.querySelector('[name="bulkmovecategories"]');
            if (!destination || !move) {
                return;
            }
            // Choosing a destination is harmless before categories have been checked.
            if (destination.disabled) {
                destination.disabled = false;
            }
            const hasSelection = !!root.querySelector('input[name="bcat[]"]:checked:not(:disabled)');
            move.disabled = !hasSelection || destination.value === '';
        }
        function enhance(select) {
            if (select.multiple || select.dataset.westfieldMenu) {
                return;
            }
            select.dataset.westfieldMenu = 'true';
            const wrapper = document.createElement('div');
            wrapper.className = 'dropdown westfield-select-menu';
            const trigger = document.createElement('button');
            trigger.type = 'button';
            trigger.className = 'btn dropdown-toggle westfield-select-toggle';
            trigger.id = 'westfield-select-' + (++sequence);
            trigger.setAttribute('aria-haspopup', 'listbox');
            trigger.setAttribute('aria-expanded', 'false');
            const menu = document.createElement('div');
            menu.id = trigger.id + '-options';
            menu.className = 'dropdown-menu westfield-select-options';
            menu.setAttribute('role', 'listbox');
            trigger.setAttribute('aria-controls', menu.id);
            const label = select.getAttribute('aria-label') || (select.labels && select.labels[0]?.textContent.trim());
            if (select.hasAttribute('aria-labelledby')) {
                trigger.setAttribute('aria-labelledby', select.getAttribute('aria-labelledby') + ' ' + trigger.id);
                menu.setAttribute('aria-labelledby', select.getAttribute('aria-labelledby'));
            }
            for (const attribute of ['aria-describedby', 'title']) {
                if (select.hasAttribute(attribute)) {
                    trigger.setAttribute(attribute, select.getAttribute(attribute));
                }
            }
            function close(restoreFocus) {
                menu.classList.remove('show');
                trigger.setAttribute('aria-expanded', 'false');
                if (restoreFocus) {
                    trigger.focus();
                }
            }
            function sync() {
                if (select.name === 'movecategoriesto') {
                    syncCategoryMove();
                }
                trigger.disabled = select.disabled;
                trigger.textContent = select.selectedOptions[0]?.textContent.trim() || '';
                if (label) {
                    trigger.setAttribute('aria-label', label + ': ' + trigger.textContent);
                    menu.setAttribute('aria-label', label);
                }
                const selected = select.selectedIndex;
                menu.querySelectorAll('[role="option"]').forEach((option, index) => {
                    option.disabled = select.options[index].disabled || select.options[index].parentElement.disabled === true;
                    option.setAttribute('aria-selected', String(index === selected));
                    option.classList.toggle('active', index === selected);
                });
                if (select.disabled) {
                    close(false);
                }
            }
            function rebuild() {
                menu.replaceChildren();
                Array.from(select.options).forEach((option, index) => {
                    const item = document.createElement('button');
                    item.type = 'button';
                    item.className = 'dropdown-item';
                    item.setAttribute('role', 'option');
                    item.tabIndex = -1;
                    item.textContent = option.textContent;
                    item.hidden = option.hidden;
                    item.addEventListener('click', () => {
                        if (select.disabled || item.disabled) {
                            return;
                        }
                        const changed = select.selectedIndex !== index;
                        select.selectedIndex = index;
                        sync();
                        close(true);
                        if (changed) {
                            select.dispatchEvent(new Event('change', {bubbles: true}));
                        }
                    });
                    menu.append(item);
                });
                sync();
            }
            function options() {
                return Array.from(menu.children).filter(item => !item.disabled && !item.hidden);
            }
            function open(last) {
                if (select.disabled) {
                    return;
                }
                menus.forEach(entry => entry.close(false));
                sync();
                menu.classList.add('show');
                trigger.setAttribute('aria-expanded', 'true');
                const items = options();
                (last ? items[items.length - 1] : items.find(item => item.getAttribute('aria-selected') === 'true') || items[0])?.focus();
            }
            trigger.addEventListener('click', () => menu.classList.contains('show') ? close(false) : open(false));
            trigger.addEventListener('keydown', event => {
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    open(event.key === 'ArrowUp');
                }
            });
            let typed = '';
            let typedAt = 0;
            menu.addEventListener('keydown', event => {
                const items = options();
                const index = items.indexOf(document.activeElement);
                let next;
                if (event.key === 'Escape') {
                    event.preventDefault();
                    close(true);
                } else if (event.key === 'Tab') {
                    close(true);
                } else if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
                    event.preventDefault();
                    next = event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1 :
                        (index + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
                    items[next]?.focus();
                } else if (event.key.length === 1 && !event.ctrlKey && !event.metaKey && event.key !== ' ') {
                    typed = Date.now() - typedAt > 700 ? event.key : typed + event.key;
                    typedAt = Date.now();
                    items.find(item => item.textContent.trim().toLowerCase().startsWith(typed.toLowerCase()))?.focus();
                }
            });
            wrapper.append(trigger, menu);
            select.after(wrapper);
            select.hidden = true;
            select.addEventListener('change', sync);
            new MutationObserver(records => {
                if (records.some(record => record.type === 'childList' || record.type === 'characterData')) {
                    rebuild();
                } else {
                    sync();
                }
            }).observe(select, {attributes: true, childList: true, subtree: true, characterData: true});
            select.form?.addEventListener('reset', () => setTimeout(sync, 0));
            menus.push({wrapper, close});
            rebuild();
        }
        root.querySelectorAll('.bulk-actions select, #action_bar select').forEach(enhance);
        root.addEventListener('change', syncCategoryMove);
        root.querySelector('#coursecat-management')?.addEventListener('reset', () => setTimeout(syncCategoryMove, 0));
        syncCategoryMove();
        document.addEventListener('click', event => {
            menus.forEach(entry => {
                if (!entry.wrapper.contains(event.target)) {
                    entry.close(false);
                }
            });
        });
        document.addEventListener('focusin', event => {
            menus.forEach(entry => {
                if (!entry.wrapper.contains(event.target)) {
                    entry.close(false);
                }
            });
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, {once: true});
    } else {
        init();
    }
})();
