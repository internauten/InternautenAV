{$internautenav_order_protocol_panel_html nofilter}

{if $internautenav_admin_order_load_style}
    <link rel="stylesheet" href="{$internautenav_admin_order_css_url|escape:'htmlall':'UTF-8'}">
{/if}

<div class="card mt-2">
    <div class="card-header">
        <h3>{$internautenav_uploaded_documents_title|escape:'htmlall':'UTF-8'}</h3>
    </div>
    <div class="card-body">
        {if empty($internautenav_uploaded_documents_rows)}
            <p class="text-muted">{$internautenav_uploaded_documents_empty|escape:'htmlall':'UTF-8'}</p>
        {else}
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>{$internautenav_uploaded_documents_col_id|escape:'htmlall':'UTF-8'}</th>
                            <th>{$internautenav_uploaded_documents_col_name|escape:'htmlall':'UTF-8'}</th>
                            <th>{$internautenav_uploaded_documents_col_type|escape:'htmlall':'UTF-8'}</th>
                            <th>{$internautenav_uploaded_documents_col_mime|escape:'htmlall':'UTF-8'}</th>
                            <th>{$internautenav_uploaded_documents_col_size|escape:'htmlall':'UTF-8'}</th>
                            <th>{$internautenav_uploaded_documents_col_created_at|escape:'htmlall':'UTF-8'}</th>
                            <th>{$internautenav_uploaded_documents_col_action|escape:'htmlall':'UTF-8'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$internautenav_uploaded_documents_rows item=row}
                            <tr>
                                <td>{$row.id|intval}</td>
                                <td>{$row.original_name|escape:'htmlall':'UTF-8'}</td>
                                <td><span class="badge">{$row.doc_type|escape:'htmlall':'UTF-8'}</span></td>
                                <td><small class="text-muted">{$row.mime_type|escape:'htmlall':'UTF-8'}</small></td>
                                <td>{$row.file_size|escape:'htmlall':'UTF-8'}</td>
                                <td>{$row.created_at|escape:'htmlall':'UTF-8'}</td>
                                <td>
                                    <button type="button" class="btn btn-default btn-xs js-internautenav-preview"
                                        data-order-id="{$internautenav_order_id|intval}"
                                        data-preview-url="{$row.preview_url|escape:'htmlall':'UTF-8'}"
                                        data-file-name="{$row.original_name|escape:'htmlall':'UTF-8'}">
                                        <i class="icon-eye"></i>
                                        {$internautenav_uploaded_documents_preview|escape:'htmlall':'UTF-8'}
                                    </button>
                                </td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        {/if}
    </div>
</div>

<div id="internautenav-preview-modal-{$internautenav_order_id|intval}" class="internautenav-admin-modal"
    data-msg-confirm-approve="{$internautenav_admin_order_msg_confirm_approve|escape:'htmlall':'UTF-8'}"
    data-msg-confirm-reject="{$internautenav_admin_order_msg_confirm_reject|escape:'htmlall':'UTF-8'}"
    data-msg-ok-approve="{$internautenav_admin_order_msg_ok_approve|escape:'htmlall':'UTF-8'}"
    data-msg-ok-reject="{$internautenav_admin_order_msg_ok_reject|escape:'htmlall':'UTF-8'}"
    data-msg-error-prefix="{$internautenav_admin_order_msg_error_prefix|escape:'htmlall':'UTF-8'}"
    data-msg-error-unknown="{$internautenav_admin_order_msg_error_unknown|escape:'htmlall':'UTF-8'}"
    data-msg-error-connection="{$internautenav_admin_order_msg_error_connection|escape:'htmlall':'UTF-8'}">
    <div class="internautenav-admin-modal-dialog">
        <button type="button"
            class="btn btn-link js-internautenav-modal-close internautenav-admin-modal-close">&times;</button>
        <h4 class="internautenav-admin-modal-title">{$internautenav_admin_order_modal_title|escape:'htmlall':'UTF-8'}
        </h4>
        <div id="internautenav-preview-filename-{$internautenav_order_id|intval}"
            class="text-muted internautenav-admin-preview-filename"></div>
        <div class="internautenav-admin-preview-stage">
            <img id="internautenav-preview-image-{$internautenav_order_id|intval}" src=""
                alt="{$internautenav_admin_order_modal_title|escape:'htmlall':'UTF-8'}"
                class="internautenav-admin-preview-image">
        </div>
        <div class="internautenav-admin-modal-actions">
            <button type="button" class="btn btn-success btn-sm js-internautenav-modal-action" data-action="approve"
                data-order-id="{$internautenav_order_id|intval}"
                data-token="{$internautenav_admin_order_token|escape:'htmlall':'UTF-8'}"
                data-ajax-url="{$internautenav_admin_order_ajax_url|escape:'htmlall':'UTF-8'}">
                <i class="icon-ok"></i> {$internautenav_admin_order_modal_approve|escape:'htmlall':'UTF-8'}
            </button>
            <button type="button" class="btn btn-danger btn-sm js-internautenav-modal-action" data-action="reject"
                data-order-id="{$internautenav_order_id|intval}"
                data-token="{$internautenav_admin_order_token|escape:'htmlall':'UTF-8'}"
                data-ajax-url="{$internautenav_admin_order_ajax_url|escape:'htmlall':'UTF-8'}">
                <i class="icon-remove"></i> {$internautenav_admin_order_modal_reject|escape:'htmlall':'UTF-8'}
            </button>
            <button type="button"
                class="btn btn-default btn-sm js-internautenav-modal-close">{$internautenav_admin_order_modal_close|escape:'htmlall':'UTF-8'}</button>
            <span class="text-muted internautenav-admin-modal-hint"><i class="icon-shield"></i>
                {$internautenav_admin_order_modal_hint|escape:'htmlall':'UTF-8'}</span>
        </div>
    </div>
</div>

{if $internautenav_admin_order_load_script}
    <script src="{$internautenav_admin_order_js_url|escape:'htmlall':'UTF-8'}"></script>
{/if}