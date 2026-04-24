(function (window, document) {
    function getScopedFields(form, selector) {
        if (!form) {
            return [];
        }

        const scoped = Array.from(form.querySelectorAll(selector));
        const external = form.id
            ? Array.from(document.querySelectorAll(selector + '[form="' + form.id + '"]'))
            : [];
        const seen = new Set();

        return scoped.concat(external).filter(function (field) {
            if (seen.has(field)) {
                return false;
            }
            seen.add(field);
            return true;
        });
    }

    function initTimePage(options) {
        const config = options || {};
        const historyContainer = document.getElementById(config.historyId || 'timeHistoryList');
        const filterForm = document.getElementById(config.filterFormId || 'timeHistoryFilterForm');
        const modalElement = document.getElementById(config.modalId || 'ajaxEditTimeLogModal');
        const modalContent = document.getElementById(config.modalContentId || 'ajaxEditTimeLogModalContent');
        const messageContainer = document.getElementById(config.messageId || 'timePageMessage');

        if (!historyContainer || !filterForm || !modalElement || !modalContent || typeof bootstrap === 'undefined') {
            return;
        }

        const modalInstance = bootstrap.Modal.getOrCreateInstance(modalElement);
        const pageStateKey = config.pageStateKey || filterForm.getAttribute('data-page-state-key') || '';
        const canUsePageState = !!(window.PageState && pageStateKey);
        const loadingApi = window.GlobalLoading || null;
        let restoredStateOnLoad = false;
        let searchTimer = null;

        function setMessage(message, type) {
            if (!messageContainer) {
                return;
            }

            messageContainer.innerHTML = message
                ? '<div class="alert alert-' + type + ' rounded-4 mb-4">' + message + '</div>'
                : '';
        }

        function setModalFeedback(message, type) {
            let feedback = modalContent.querySelector('[data-modal-feedback]');
            if (!feedback) {
                const form = modalContent.querySelector('[data-ajax-edit-form]');
                if (!form || !form.parentNode) {
                    return;
                }

                feedback = document.createElement('div');
                feedback.setAttribute('data-modal-feedback', '1');
                feedback.className = 'mb-3';
                form.parentNode.insertBefore(feedback, form);
            }

            feedback.innerHTML = message
                ? '<div class="alert alert-' + type + ' rounded-4 mb-0">' + message + '</div>'
                : '';
        }

        function savePageState() {
            if (canUsePageState) {
                window.PageState.saveFormState(pageStateKey, filterForm);
            }
        }

        function currentQuery(pageOverride, resetPage) {
            const params = new URLSearchParams(new FormData(filterForm));

            if (pageOverride) {
                params.set('p', pageOverride);
            } else if (resetPage) {
                params.set('p', '1');
            }

            return params.toString();
        }

        async function refreshHistory(queryString, pushState) {
            historyContainer.innerHTML = '<div class="ops-loading">กำลังโหลดข้อมูล...</div>';

            const response = await fetch('../ajax/time/history_rows.php?' + queryString, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            historyContainer.innerHTML = await response.text();
            bindAsync();

            if (pushState) {
                window.history.replaceState({}, '', 'time.php?' + queryString);
            }
        }

        function forceCloseModal() {
            modalInstance.hide();
            modalElement.classList.remove('show');
            modalElement.style.display = 'none';
            modalElement.setAttribute('aria-hidden', 'true');
            modalElement.removeAttribute('aria-modal');
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('padding-right');
            document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
                backdrop.remove();
            });
        }

        async function parseJsonResponse(response) {
            const raw = await response.text();

            if (!raw) {
                return {
                    success: false,
                    message: 'ไม่พบข้อมูลตอบกลับจากระบบ'
                };
            }

            try {
                return JSON.parse(raw);
            } catch (error) {
                return {
                    success: false,
                    message: 'เกิดข้อผิดพลาดในการเชื่อมต่อ กรุณาลองใหม่อีกครั้ง'
                };
            }
        }

        function clearLoading(trigger) {
            if (loadingApi && typeof loadingApi.hideGlobalLoading === 'function') {
                loadingApi.hideGlobalLoading({ trigger: trigger || null });
            }
        }

        async function requestWithLoading(request, message, trigger) {
            if (loadingApi && typeof loadingApi.withGlobalLoading === 'function') {
                return loadingApi.withGlobalLoading(request, message, { trigger: trigger || null });
            }

            return request;
        }

        async function openEdit(id, href) {
            modalContent.innerHTML = '<div class="modal-body text-center py-5 text-muted">กำลังโหลดข้อมูล...</div>';
            modalInstance.show();

            const url = new URL(href, window.location.href);
            const response = await fetch(
                '../ajax/time/get_time_log.php?id=' + encodeURIComponent(id) +
                '&p=' + encodeURIComponent(url.searchParams.get('p') || '1') +
                '&date=' + encodeURIComponent(url.searchParams.get('date') || '') +
                '&date_from=' + encodeURIComponent(url.searchParams.get('date_from') || '') +
                '&date_to=' + encodeURIComponent(url.searchParams.get('date_to') || '') +
                '&status=' + encodeURIComponent(url.searchParams.get('status') || 'all') +
                '&query=' + encodeURIComponent(url.searchParams.get('query') || ''),
                { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
            );

            modalContent.innerHTML = await response.text();

            const form = modalContent.querySelector('[data-ajax-edit-form]');
            if (!form) {
                return;
            }

            const deleteButton = form.querySelector('[data-delete-submit]');
            const saveButton = form.querySelector('button[type="submit"]');

            async function handleFormSubmit(submitter) {
                if (!submitter) {
                    return;
                }

                const isDelete = submitter.hasAttribute('data-delete-submit');
                const formData = new FormData(form);

                if (isDelete) {
                    const confirmMessage = submitter.getAttribute('data-confirm-message')
                        || 'ยืนยันการลบรายการลงเวลาเวรนี้ใช่หรือไม่?';

                    if (!window.confirm(confirmMessage)) {
                        return;
                    }

                    formData.set('delete_time_log', '1');
                    setModalFeedback('', 'danger');
                    submitter.disabled = true;
                    if (saveButton) {
                        saveButton.disabled = true;
                    }

                    try {
                        const deleteResponse = await requestWithLoading(fetch('../ajax/time/delete_time_log.php', {
                            method: 'POST',
                            body: formData,
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        }), 'กำลังลบรายการ...', submitter);
                        const deleteResult = await parseJsonResponse(deleteResponse);

                        if (deleteResponse.ok && deleteResult.success) {
                            if (loadingApi && typeof loadingApi.showPageNavigationLoading === 'function') {
                                loadingApi.showPageNavigationLoading('กำลังรีเฟรชข้อมูล...', { trigger: submitter });
                            }
                            forceCloseModal();
                            setMessage(deleteResult.message || 'ลบรายการลงเวลาเวรเรียบร้อยแล้ว', 'success');
                            window.setTimeout(function () {
                                window.location.reload();
                            }, 120);
                        } else {
                            clearLoading(submitter);
                            setModalFeedback(deleteResult.message || 'ไม่สามารถลบรายการได้', 'danger');
                        }
                    } catch (error) {
                        clearLoading(submitter);
                        setModalFeedback('เกิดข้อผิดพลาดในการเชื่อมต่อ กรุณาลองใหม่อีกครั้ง', 'danger');
                    } finally {
                        submitter.disabled = false;
                        if (saveButton) {
                            saveButton.disabled = false;
                        }
                    }

                    return;
                }

                const submitterName = submitter.getAttribute('name');
                const submitterValue = submitter.value || '1';

                if (submitterName) {
                    formData.set(submitterName, submitterValue);
                }

                setModalFeedback('', 'danger');
                if (saveButton) {
                    saveButton.disabled = true;
                }
                if (deleteButton) {
                    deleteButton.disabled = true;
                }

                try {
                    const submitResponse = await requestWithLoading(fetch('../ajax/time/update_time_log.php', {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    }), 'กำลังบันทึกข้อมูล...', saveButton || submitter);
                    const result = await parseJsonResponse(submitResponse);

                    if (submitResponse.ok && result.success) {
                        forceCloseModal();
                        setMessage(result.message || 'บันทึกข้อมูลเรียบร้อยแล้ว', 'success');
                        await refreshHistory(currentQuery(null, false), false);
                    } else {
                        clearLoading(saveButton || submitter);
                        setModalFeedback(result.message || 'ไม่สามารถบันทึกข้อมูลได้', 'danger');
                    }
                } catch (error) {
                    clearLoading(saveButton || submitter);
                    setModalFeedback('เกิดข้อผิดพลาดในการเชื่อมต่อ กรุณาลองใหม่อีกครั้ง', 'danger');
                } finally {
                    if (saveButton) {
                        saveButton.disabled = false;
                    }
                    if (deleteButton) {
                        deleteButton.disabled = false;
                    }
                }
            }

            form.addEventListener('submit', async function (event) {
                event.preventDefault();
                const submitter = event.submitter || form.querySelector('button[type="submit"]');
                await handleFormSubmit(submitter);
            });

            if (deleteButton) {
                deleteButton.addEventListener('click', async function () {
                    await handleFormSubmit(deleteButton);
                });
            }
        }

        function bindAsync() {
            historyContainer.querySelectorAll('[data-time-edit-link]').forEach(function (link) {
                link.addEventListener('click', function (event) {
                    event.preventDefault();
                    openEdit(link.getAttribute('data-id'), link.href);
                });
            });

            historyContainer.querySelectorAll('[data-time-history-page]').forEach(function (link) {
                link.addEventListener('click', function (event) {
                    event.preventDefault();
                    const href = new URL(link.href, window.location.href);
                    savePageState();
                    refreshHistory(href.searchParams.toString(), true);
                });
            });
        }

        function bindFilterAutoRefresh() {
            filterForm.addEventListener('submit', function (event) {
                event.preventDefault();
                savePageState();
                refreshHistory(currentQuery(null, true), true);
            });

            getScopedFields(filterForm, 'select, input[type="date"], input[type="month"], input[type="number"]').forEach(function (field) {
                field.addEventListener('change', function () {
                    savePageState();
                    refreshHistory(currentQuery(null, true), true);
                });
            });

            getScopedFields(filterForm, 'input[type="text"], input[type="search"]').forEach(function (field) {
                field.addEventListener('input', function () {
                    window.clearTimeout(searchTimer);
                    searchTimer = window.setTimeout(function () {
                        savePageState();
                        refreshHistory(currentQuery(null, true), true);
                    }, 350);
                });

                field.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        window.clearTimeout(searchTimer);
                        savePageState();
                        refreshHistory(currentQuery(null, true), true);
                    }
                });
            });
        }

        modalElement.addEventListener('hidden.bs.modal', function () {
            modalContent.innerHTML = '<div class="modal-body text-center py-5 text-muted">กำลังโหลดข้อมูล...</div>';
        });

        if (canUsePageState) {
            const restoreResult = window.PageState.restoreFormState({
                pageKey: pageStateKey,
                form: filterForm
            });
            restoredStateOnLoad = !!restoreResult.restored;

            if (!restoredStateOnLoad) {
                savePageState();
            }
        }

        bindFilterAutoRefresh();
        bindAsync();

        if (restoredStateOnLoad) {
            refreshHistory(currentQuery(null, true), true);
        }
    }

    window.TimePageAsync = { init: initTimePage };
})(window, document);
