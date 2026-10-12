/* Nerasky — accessible animated select menus */
(function () {
  'use strict';

  var activeInstance = null;
  var sequence = 0;

  function makeId(base) {
    var id = base;
    while (document.getElementById(id)) id += '-ui';
    return id;
  }

  function isOptionDisabled(option) {
    return option.disabled || Boolean(option.parentElement && option.parentElement.disabled);
  }

  function enabledOptions(instance) {
    return Array.prototype.slice.call(instance.select.options).filter(function (option) {
      return !option.hidden && !isOptionDisabled(option);
    });
  }

  function sync(instance) {
    var select = instance.select;
    var option = select.options[select.selectedIndex];
    instance.label.textContent = option ? option.textContent.trim() : '';
    instance.trigger.disabled = select.disabled;
    instance.trigger.setAttribute('aria-required', select.required ? 'true' : 'false');
    var invalid = select.classList.contains('input-error') || (instance.touchedInvalid && !select.validity.valid);
    instance.trigger.setAttribute('aria-invalid', invalid ? 'true' : 'false');
    instance.trigger.classList.toggle('input-error', invalid);
    instance.trigger.classList.toggle('is-placeholder', !option || option.value === '');
    if (instance.isOpen) renderOptions(instance);
  }

  function position(instance) {
    if (!instance || !instance.isOpen) return;
    var rect = instance.trigger.getBoundingClientRect();
    var roomBelow = window.innerHeight - rect.bottom - 12;
    var roomAbove = rect.top - 12;
    var openAbove = roomBelow < 240 && roomAbove > roomBelow;
    var room = Math.max(100, openAbove ? roomAbove : roomBelow);
    instance.menu.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - Math.min(rect.width, window.innerWidth - 16) - 8)) + 'px';
    instance.menu.style.width = Math.min(rect.width, window.innerWidth - 16) + 'px';
    instance.menu.style.maxHeight = Math.min(320, room) + 'px';
    instance.menu.classList.toggle('opens-above', openAbove);
    var menuHeight = instance.menu.getBoundingClientRect().height;
    instance.menu.style.top = (openAbove
      ? Math.max(8, rect.top - menuHeight - 8)
      : Math.min(window.innerHeight - menuHeight - 8, rect.bottom + 8)) + 'px';
  }

  function setActive(instance, option, scroll) {
    if (!option) return;
    instance.activeOption = option;
    Array.prototype.forEach.call(instance.menu.querySelectorAll('[role="option"]'), function (item) {
      var active = item.getAttribute('data-value') === option.value && Number(item.getAttribute('data-index')) === option.index;
      item.classList.toggle('is-active', active);
      if (active) {
        instance.trigger.setAttribute('aria-activedescendant', item.id);
        if (scroll) item.scrollIntoView({ block: 'nearest' });
      }
    });
  }

  function renderOptions(instance) {
    var menu = instance.menu;
    menu.textContent = '';
    var list = document.createElement('div');
    list.className = 'select-ui-options';
    list.setAttribute('role', 'presentation');

    Array.prototype.forEach.call(instance.select.children, function (child) {
      if (child.tagName === 'OPTGROUP') {
        var group = document.createElement('div');
        group.className = 'select-ui-group';
        group.setAttribute('role', 'group');
        group.setAttribute('aria-label', child.label);
        var heading = document.createElement('div');
        heading.className = 'select-ui-group-label';
        heading.textContent = child.label;
        group.appendChild(heading);
        Array.prototype.forEach.call(child.children, function (option) {
          if (option.tagName === 'OPTION') appendOption(instance, group, option);
        });
        list.appendChild(group);
      } else if (child.tagName === 'OPTION') {
        appendOption(instance, list, child);
      }
    });
    menu.appendChild(list);
    var selected = instance.select.options[instance.select.selectedIndex];
    var initial = selected && !isOptionDisabled(selected) ? selected : enabledOptions(instance)[0];
    setActive(instance, initial, false);
  }

  function appendOption(instance, container, option) {
    if (option.hidden) return;
    var disabled = isOptionDisabled(option);
    var item = document.createElement('div');
    item.className = 'select-ui-option';
    item.id = instance.id + '-option-' + option.index;
    item.setAttribute('role', 'option');
    item.setAttribute('data-index', String(option.index));
    item.setAttribute('data-value', option.value);
    item.setAttribute('aria-selected', option.selected ? 'true' : 'false');
    item.setAttribute('aria-disabled', disabled ? 'true' : 'false');
    if (disabled) item.classList.add('is-disabled');

    var text = document.createElement('span');
    text.className = 'select-ui-option-text';
    text.textContent = option.textContent.trim();
    var check = document.createElement('span');
    check.className = 'select-ui-check';
    check.setAttribute('aria-hidden', 'true');
    check.textContent = '✓';
    item.appendChild(text);
    item.appendChild(check);
    item.addEventListener('pointerenter', function () {
      if (!disabled) setActive(instance, option, false);
    });
    item.addEventListener('click', function () {
      if (disabled) return;
      instance.select.selectedIndex = option.index;
      sync(instance);
      close(instance);
      instance.trigger.focus();
      instance.select.dispatchEvent(new Event('input', { bubbles: true }));
      instance.select.dispatchEvent(new Event('change', { bubbles: true }));
    });
    container.appendChild(item);
  }

  function close(instance) {
    if (!instance || !instance.isOpen) return;
    instance.isOpen = false;
    instance.trigger.setAttribute('aria-expanded', 'false');
    instance.trigger.removeAttribute('aria-activedescendant');
    instance.menu.classList.remove('is-open');
    window.setTimeout(function () {
      if (!instance.menu.classList.contains('is-open')) instance.menu.hidden = true;
    }, 150);
    if (activeInstance === instance) activeInstance = null;
  }

  function open(instance) {
    if (instance.select.disabled) return;
    if (activeInstance && activeInstance !== instance) close(activeInstance);
    activeInstance = instance;
    renderOptions(instance);
    instance.isOpen = true;
    instance.menu.hidden = false;
    instance.trigger.setAttribute('aria-expanded', 'true');
    position(instance);
    window.requestAnimationFrame(function () {
      instance.menu.classList.add('is-open');
    });
  }

  function moveActive(instance, direction, edge) {
    var options = enabledOptions(instance);
    if (!options.length) return;
    var current = instance.activeOption ? options.indexOf(instance.activeOption) : -1;
    var next = edge === 'first' ? 0 : edge === 'last' ? options.length - 1 : Math.max(0, Math.min(options.length - 1, current + direction));
    setActive(instance, options[next], true);
  }

  function init(select) {
    if (select.multiple || select.size > 1 || select.dataset.selectUi === 'off') return;
    var id = select.id || ('select-ui-native-' + (++sequence));
    select.id = id;
    var triggerId = makeId(id + '-control');
    var parent = select.parentNode;
    if (!parent) return;

    var computed = window.getComputedStyle(select);
    var wrapper = document.createElement('span');
    wrapper.className = 'select-ui';
    if (select.style.width === 'auto') wrapper.classList.add('is-compact');
    if (select.classList.contains('browse-sort-select')) wrapper.classList.add('select-ui-sort');
    if (select.style.width) wrapper.style.width = select.style.width;
    if (select.style.flex) wrapper.style.flex = select.style.flex;
    if (select.style.flexBasis) wrapper.style.flexBasis = select.style.flexBasis;
    if (select.style.minWidth) wrapper.style.minWidth = select.style.minWidth;
    if (select.style.maxWidth) wrapper.style.maxWidth = select.style.maxWidth;
    if (select.style.margin) wrapper.style.margin = select.style.margin;

    var trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.id = triggerId;
    trigger.className = 'select-ui-trigger ' + select.className;
    trigger.setAttribute('role', 'combobox');
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-controls', id + '-menu');
    if (select.getAttribute('aria-label')) trigger.setAttribute('aria-label', select.getAttribute('aria-label'));
    if (select.required) trigger.setAttribute('aria-required', 'true');
    if (select.disabled) trigger.disabled = true;
    if (select.style.width) trigger.style.width = select.style.width;

    var label = document.createElement('span');
    label.className = 'select-ui-value';
    var arrow = document.createElement('span');
    arrow.className = 'select-ui-chevron';
    arrow.setAttribute('aria-hidden', 'true');
    arrow.innerHTML = '<svg viewBox="0 0 16 16" focusable="false"><path d="m4 6 4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    trigger.appendChild(label);
    trigger.appendChild(arrow);

    var menu = document.createElement('div');
    menu.className = 'select-ui-menu';
    menu.id = id + '-menu';
    menu.setAttribute('role', 'listbox');
    if (select.getAttribute('aria-label')) menu.setAttribute('aria-label', select.getAttribute('aria-label'));
    menu.hidden = true;
    document.body.appendChild(menu);

    var instance = { id: id, select: select, wrapper: wrapper, trigger: trigger, label: label, menu: menu, activeOption: null, typeBuffer: '', typeTimer: 0, touchedInvalid: false, isOpen: false };
    parent.insertBefore(wrapper, select);
    wrapper.appendChild(trigger);
    wrapper.appendChild(select);
    select.classList.add('select-ui-native');
    select.hidden = true;
    select.setAttribute('aria-hidden', 'true');
    select.setAttribute('role', 'none');
    select.tabIndex = -1;

    Array.prototype.forEach.call(document.querySelectorAll('label[for]'), function (item) {
      if (item.getAttribute('for') === id) item.setAttribute('for', triggerId);
    });
    if (!trigger.hasAttribute('aria-label')) {
      var labelledBy = [];
      Array.prototype.forEach.call(document.querySelectorAll('label'), function (item) {
        if (item.htmlFor === triggerId || item.contains(trigger)) {
          if (!item.id) item.id = triggerId + '-label';
          labelledBy.push(item.id);
        }
      });
      if (labelledBy.length) trigger.setAttribute('aria-labelledby', labelledBy.join(' '));
    }
    if (trigger.hasAttribute('aria-labelledby')) menu.setAttribute('aria-labelledby', trigger.getAttribute('aria-labelledby'));

    sync(instance);
    select.addEventListener('change', function () { sync(instance); });
    select.addEventListener('input', function () { sync(instance); });
    select.addEventListener('invalid', function (event) {
      event.preventDefault();
      instance.touchedInvalid = true;
      sync(instance);
      trigger.setAttribute('aria-invalid', 'true');
      trigger.focus();
      open(instance);
    });
    if (select.form) {
      select.form.addEventListener('reset', function () {
        window.setTimeout(function () { sync(instance); }, 0);
      });
    }

    trigger.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();
      if (!instance.isOpen) open(instance);
      else close(instance);
    });
    trigger.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        if (!instance.isOpen) open(instance);
        moveActive(instance, event.key === 'ArrowDown' ? 1 : -1);
      } else if (event.key === 'Home' || event.key === 'End') {
        event.preventDefault();
        if (!instance.isOpen) open(instance);
        moveActive(instance, 0, event.key === 'Home' ? 'first' : 'last');
      } else if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        if (!instance.isOpen) open(instance);
        else if (instance.activeOption && !isOptionDisabled(instance.activeOption)) {
          instance.select.selectedIndex = instance.activeOption.index;
          sync(instance);
          close(instance);
          instance.select.dispatchEvent(new Event('input', { bubbles: true }));
          instance.select.dispatchEvent(new Event('change', { bubbles: true }));
        }
      } else if (event.key === 'Escape' && instance.isOpen) {
        event.preventDefault();
        close(instance);
      } else if (event.key === 'Tab' && instance.isOpen) {
        close(instance);
      } else if (event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
        instance.typeBuffer += event.key.toLocaleLowerCase();
        window.clearTimeout(instance.typeTimer);
        instance.typeTimer = window.setTimeout(function () { instance.typeBuffer = ''; }, 650);
        var match = enabledOptions(instance).find(function (option) {
          return option.textContent.trim().toLocaleLowerCase().indexOf(instance.typeBuffer) === 0;
        });
        if (match) {
          if (!instance.isOpen) open(instance);
          setActive(instance, match, true);
        }
      }
    });
  }

  document.addEventListener('click', function (event) {
    if (activeInstance && !activeInstance.wrapper.contains(event.target) && !activeInstance.menu.contains(event.target)) close(activeInstance);
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && activeInstance) close(activeInstance);
  });
  window.addEventListener('resize', function () { position(activeInstance); });
  window.addEventListener('scroll', function () { position(activeInstance); }, true);

  Array.prototype.forEach.call(document.querySelectorAll('select'), init);
})();
