(function() {
    const initialize = function() {
        const countInput = document.querySelector('[name="installmentcount"]');
        if (!countInput) {
            return;
        }

        const maxInstallments = Number.parseInt(countInput.dataset.maxInstallments, 10);
        const fields = ['header', 'amount', 'date'];
        const updateVisibility = function() {
            const count = Math.max(0, Math.min(maxInstallments, Number.parseInt(countInput.value, 10) || 0));
            for (let index = 1; index <= maxInstallments; index++) {
                const hidden = index > count;
                fields.forEach(function(field) {
                    const wrapper = document.getElementById('fitem_id_installment' + field + '_' + index);
                    if (wrapper) {
                        wrapper.classList.toggle('d-none', hidden);
                        wrapper.querySelectorAll('input, select').forEach(function(control) {
                            control.disabled = hidden;
                        });
                    }
                });
            }
        };

        countInput.addEventListener('input', updateVisibility);
        updateVisibility();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }
})();
