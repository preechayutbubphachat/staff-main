<?php

function render_staff_profile_modal(string $modalId = 'staffProfileModal', string $bodyId = 'staffProfileModalBody'): void
{
    ?>
    <div class="modal fade" id="<?= htmlspecialchars($modalId) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content rounded-4 shadow-lg border-0">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <div class="text-uppercase small fw-bold text-muted">Profile Preview</div>
                        <h5 class="modal-title">ข้อมูลเจ้าหน้าที่</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="<?= htmlspecialchars($bodyId) ?>">
                    <div class="text-center py-5 text-muted">กำลังโหลดข้อมูล...</div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function render_staff_profile_modal_script(string $modalId = 'staffProfileModal', string $bodyId = 'staffProfileModalBody'): void
{
    ?>
    <script>
    (function () {
        const modalElement = document.getElementById('<?= addslashes($modalId) ?>');
        const modalBody = document.getElementById('<?= addslashes($bodyId) ?>');
        if (!modalElement || !modalBody || typeof bootstrap === 'undefined') {
            return;
        }

        const modalInstance = bootstrap.Modal.getOrCreateInstance(modalElement);

        async function openStaffProfile(userId) {
            if (!userId) {
                return;
            }

            modalBody.innerHTML = '<div class="text-center py-5 text-muted">กำลังโหลดข้อมูล...</div>';
            modalInstance.show();

            try {
                const response = await fetch('staff_profile_modal.php?id=' + encodeURIComponent(userId), {
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
            openStaffProfile(trigger.getAttribute('data-user-id'));
        });
    })();
    </script>
    <?php
}
