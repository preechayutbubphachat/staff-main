(function (window, document) {
    const fullMonths = [
        '',
        'มกราคม',
        'กุมภาพันธ์',
        'มีนาคม',
        'เมษายน',
        'พฤษภาคม',
        'มิถุนายน',
        'กรกฎาคม',
        'สิงหาคม',
        'กันยายน',
        'ตุลาคม',
        'พฤศจิกายน',
        'ธันวาคม'
    ];

    const shortMonths = [
        '',
        'ม.ค.',
        'ก.พ.',
        'มี.ค.',
        'เม.ย.',
        'พ.ค.',
        'มิ.ย.',
        'ก.ค.',
        'ส.ค.',
        'ก.ย.',
        'ต.ค.',
        'พ.ย.',
        'ธ.ค.'
    ];

    function isSupportedInput(input) {
        return input && (input.type === 'date' || input.type === 'month');
    }

    function formatThaiDate(value) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) {
            return '';
        }

        const parts = value.split('-');
        const year = parseInt(parts[0], 10);
        const month = parseInt(parts[1], 10);
        const day = parseInt(parts[2], 10);

        if (Number.isNaN(year) || Number.isNaN(month) || Number.isNaN(day) || !fullMonths[month]) {
            return '';
        }

        return day + ' ' + fullMonths[month] + ' ' + (year + 543);
    }

    function formatThaiMonth(value, shortLabel) {
        if (!/^\d{4}-\d{2}$/.test(value || '')) {
            return '';
        }

        const parts = value.split('-');
        const year = parseInt(parts[0], 10);
        const month = parseInt(parts[1], 10);
        const months = shortLabel ? shortMonths : fullMonths;

        if (Number.isNaN(year) || Number.isNaN(month) || !months[month]) {
            return '';
        }

        return months[month] + ' ' + (year + 543);
    }

    function buildDisplayText(input) {
        if (!isSupportedInput(input)) {
            return '';
        }

        const shortLabel = input.getAttribute('data-thai-month-style') === 'short';
        const value = String(input.value || '').trim();

        if (input.type === 'date') {
            return value ? formatThaiDate(value) : 'รูปแบบ วัน/เดือน/ปี';
        }

        return value ? formatThaiMonth(value, shortLabel) : 'รูปแบบ เดือน/ปี';
    }

    function ensureDisplayNode(input) {
        let displayNode = null;
        const describedBy = input.getAttribute('data-thai-date-display-id');

        if (describedBy) {
            displayNode = document.getElementById(describedBy);
        }

        if (!displayNode) {
            displayNode = document.createElement('div');
            displayNode.className = 'thai-date-display';
            displayNode.setAttribute('data-thai-date-display', '1');
            displayNode.id = 'thai-date-display-' + Math.random().toString(36).slice(2, 10);
            input.insertAdjacentElement('afterend', displayNode);
            input.setAttribute('data-thai-date-display-id', displayNode.id);
        }

        const ariaDescribedBy = (input.getAttribute('aria-describedby') || '').trim();
        if (!ariaDescribedBy.includes(displayNode.id)) {
            input.setAttribute('aria-describedby', (ariaDescribedBy + ' ' + displayNode.id).trim());
        }

        return displayNode;
    }

    function enhanceInput(input) {
        if (!isSupportedInput(input) || input.dataset.thaiDateUiIgnored === '1') {
            return;
        }

        input.classList.add('thai-date-input');
        input.setAttribute('lang', 'th');

        const displayNode = ensureDisplayNode(input);
        const displayText = buildDisplayText(input);

        displayNode.textContent = displayText;
        displayNode.classList.toggle('is-empty', !String(input.value || '').trim());
        input.setAttribute('title', displayText);
    }

    function enhance(root) {
        const scope = root && root.querySelectorAll ? root : document;
        const inputs = [];

        if (isSupportedInput(scope)) {
            inputs.push(scope);
        }

        scope.querySelectorAll && scope.querySelectorAll('input[type="date"], input[type="month"]').forEach(function (input) {
            inputs.push(input);
        });

        inputs.forEach(function (input) {
            enhanceInput(input);
        });
    }

    function bindEvents(root) {
        const scope = root && root.querySelectorAll ? root : document;
        const inputs = [];

        if (isSupportedInput(scope)) {
            inputs.push(scope);
        }

        scope.querySelectorAll && scope.querySelectorAll('input[type="date"], input[type="month"]').forEach(function (input) {
            inputs.push(input);
        });

        inputs.forEach(function (input) {
            if (input.dataset.thaiDateUiBound === '1') {
                return;
            }

            input.dataset.thaiDateUiBound = '1';
            ['change', 'input', 'blur'].forEach(function (eventName) {
                input.addEventListener(eventName, function () {
                    enhanceInput(input);
                });
            });
        });
    }

    function init(root) {
        enhance(root || document);
        bindEvents(root || document);
    }

    function observe() {
        if (!document.body || window.MutationObserver === undefined) {
            return;
        }

        const observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (!node || node.nodeType !== 1) {
                        return;
                    }

                    init(node);
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    window.ThaiDateUI = {
        init: init,
        enhance: enhance,
        formatThaiDate: formatThaiDate,
        formatThaiMonth: formatThaiMonth
    };

    document.addEventListener('DOMContentLoaded', function () {
        init(document);
        observe();
    });
})(window, document);
