(function (window, document) {
    function initProfileModal(options) {
        const config = options || {};
        const modalId = config.modalId || 'staffProfileModal';
        const bodyId = config.bodyId || 'staffProfileModalBody';
        const endpoint = config.endpoint || '';
        const modalElement = document.getElementById(modalId);
        const modalBody = document.getElementById(bodyId);

        if (!modalElement || !modalBody || !endpoint || typeof bootstrap === 'undefined') {
            return;
        }

        const modalInstance = bootstrap.Modal.getOrCreateInstance(modalElement);

        async function openProfile(userId) {
            if (!userId) {
                return;
            }

            modalBody.innerHTML = '<div class="text-center py-5 text-muted">กำลังโหลดข้อมูล...</div>';
            modalInstance.show();

            try {
                const response = await fetch(endpoint + '?id=' + encodeURIComponent(userId), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (!response.ok) {
                    throw new Error('โหลดข้อมูลไม่สำเร็จ');
                }

                modalBody.innerHTML = await response.text();
            } catch (error) {
                modalBody.innerHTML = '<div class="alert alert-danger rounded-4 mb-0">ไม่สามารถโหลดข้อมูลเจ้าหน้าที่ได้ กรุณาลองใหม่อีกครั้ง</div>';
            }
        }

        document.addEventListener('click', function (event) {
            const trigger = event.target.closest('[data-profile-modal-trigger]');
            if (!trigger) {
                return;
            }

            event.preventDefault();
            openProfile(trigger.getAttribute('data-user-id'));
        });
    }

    window.StaffProfileModal = {
        init: initProfileModal
    };
})(window, document);
