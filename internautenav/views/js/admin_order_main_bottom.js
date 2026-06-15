(function () {
    if (window.internautenavAdminOrderMainBottomLoaded) {
        return;
    }
    window.internautenavAdminOrderMainBottomLoaded = true;

    function getModalByOrderId(orderId) {
        return document.getElementById("internautenav-preview-modal-" + orderId);
    }

    function getLabels(orderId) {
        var modal = getModalByOrderId(orderId);
        if (!modal) {
            return {
                confirmApprove: "Pruefung als bestanden markieren und alle Dokumente DSGVO-konform loeschen?",
                confirmReject: "Pruefung als abgelehnt markieren und alle Dokumente DSGVO-konform loeschen?",
                okApprove: "Pruefung bestanden gespeichert. Dokumente geloescht.",
                okReject: "Pruefung abgelehnt gespeichert. Dokumente geloescht.",
                errorPrefix: "Fehler:",
                errorUnknown: "Unbekannter Fehler",
                errorConnection: "Verbindungsfehler beim Speichern der Entscheidung."
            };
        }

        return {
            confirmApprove: modal.getAttribute("data-msg-confirm-approve") || "",
            confirmReject: modal.getAttribute("data-msg-confirm-reject") || "",
            okApprove: modal.getAttribute("data-msg-ok-approve") || "",
            okReject: modal.getAttribute("data-msg-ok-reject") || "",
            errorPrefix: modal.getAttribute("data-msg-error-prefix") || "Fehler:",
            errorUnknown: modal.getAttribute("data-msg-error-unknown") || "Unbekannter Fehler",
            errorConnection: modal.getAttribute("data-msg-error-connection") || "Verbindungsfehler beim Speichern der Entscheidung."
        };
    }

    function runAdminAction(action, orderId, token, ajaxUrl) {
        var labels = getLabels(orderId);
        var confirmText = action === "approve" ? labels.confirmApprove : labels.confirmReject;
        var okText = action === "approve" ? labels.okApprove : labels.okReject;

        if (!confirm(confirmText)) {
            return;
        }

        var params = new URLSearchParams();
        params.append("action", "admin_" + action + "_documents");
        params.append("id_order", orderId);
        params.append("token", token);

        fetch(ajaxUrl, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: params.toString()
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    alert(data.message || okText);
                    location.reload();
                    return;
                }

                alert(labels.errorPrefix + " " + (data.message || labels.errorUnknown));
            })
            .catch(function () {
                alert(labels.errorConnection);
            });
    }

    function openPreviewModal(orderId, imageUrl, fileName) {
        var modal = getModalByOrderId(orderId);
        var image = document.getElementById("internautenav-preview-image-" + orderId);
        var nameNode = document.getElementById("internautenav-preview-filename-" + orderId);

        if (!modal || !image || !nameNode) {
            return;
        }

        image.setAttribute("src", imageUrl || "");
        nameNode.textContent = fileName || "";
        modal.style.display = "block";
    }

    function closePreviewModal(orderId) {
        var modal = getModalByOrderId(orderId);
        var image = document.getElementById("internautenav-preview-image-" + orderId);

        if (!modal) {
            return;
        }

        modal.style.display = "none";
        if (image) {
            image.setAttribute("src", "");
        }
    }

    document.addEventListener("click", function (event) {
        var previewBtn = event.target.closest(".js-internautenav-preview");
        if (previewBtn) {
            event.preventDefault();
            var previewOrderId = parseInt(previewBtn.getAttribute("data-order-id") || "0", 10);
            if (!previewOrderId) {
                return;
            }

            openPreviewModal(
                previewOrderId,
                previewBtn.getAttribute("data-preview-url") || "",
                previewBtn.getAttribute("data-file-name") || ""
            );
            return;
        }

        var closeBtn = event.target.closest(".js-internautenav-modal-close");
        if (closeBtn) {
            event.preventDefault();
            var closeModal = closeBtn.closest("[id^='internautenav-preview-modal-']");
            if (!closeModal) {
                return;
            }

            var closeOrderId = (closeModal.id || "").replace("internautenav-preview-modal-", "");
            closePreviewModal(closeOrderId);
            return;
        }

        var actionBtn = event.target.closest(".js-internautenav-modal-action");
        if (actionBtn) {
            event.preventDefault();
            var action = actionBtn.getAttribute("data-action") || "";
            var actionOrderId = parseInt(actionBtn.getAttribute("data-order-id") || "0", 10);
            var token = actionBtn.getAttribute("data-token") || "";
            var ajaxUrl = actionBtn.getAttribute("data-ajax-url") || "";

            if (!action || !actionOrderId || !token || !ajaxUrl) {
                return;
            }

            runAdminAction(action, actionOrderId, token, ajaxUrl);
        }
    });

    document.addEventListener("keydown", function (event) {
        if (event.key !== "Escape") {
            return;
        }

        var openModals = document.querySelectorAll("[id^='internautenav-preview-modal-']");
        for (var i = 0; i < openModals.length; i++) {
            if (openModals[i].style.display === "block") {
                var orderId = (openModals[i].id || "").replace("internautenav-preview-modal-", "");
                closePreviewModal(orderId);
            }
        }
    });
})();
