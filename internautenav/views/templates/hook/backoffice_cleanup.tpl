<div class="panel">
    <h3>{$internautenav_cleanup_title|escape:'htmlall':'UTF-8'}</h3>
    <table class="table" style="font-size:13px;max-width:500px">
        <tr>
            <td><strong>{$internautenav_cleanup_retention_days_label|escape:'htmlall':'UTF-8'}</strong></td>
            <td>{$internautenav_upload_retention_days|escape:'htmlall':'UTF-8'}
                {$internautenav_days_label|escape:'htmlall':'UTF-8'}</td>
        </tr>
        <tr>
            <td><strong>{$internautenav_cleanup_last_run_label|escape:'htmlall':'UTF-8'}</strong></td>
            <td>{$internautenav_last_cleanup_display|escape:'htmlall':'UTF-8'}</td>
        </tr>
        <tr>
            <td><strong>{$internautenav_cleanup_pending_total_label|escape:'htmlall':'UTF-8'}</strong></td>
            <td>{$internautenav_pending_count|escape:'htmlall':'UTF-8'}</td>
        </tr>
        <tr>
            <td><strong>{$internautenav_cleanup_pending_unassigned_label|escape:'htmlall':'UTF-8'}</strong></td>
            <td>{$internautenav_pending_unassigned_count|escape:'htmlall':'UTF-8'}</td>
        </tr>
        <tr>
            <td><strong>{$internautenav_cleanup_expired_label|escape:'htmlall':'UTF-8'}</strong></td>
            <td>{$internautenav_expired_count|escape:'htmlall':'UTF-8'}</td>
        </tr>
        <tr>
            <td><strong>{$internautenav_cron_url_label|escape:'htmlall':'UTF-8'}</strong></td>
            <td>
                <code style="word-break:break-all">{$internautenav_cron_url|escape:'htmlall':'UTF-8'}</code>
                <br><small class="text-muted">{$internautenav_cron_url_help|escape:'htmlall':'UTF-8'}</small>
            </td>
        </tr>
        <tr>
            <td><strong>{$internautenav_existing_customers_url_label|escape:'htmlall':'UTF-8'}</strong></td>
            <td>
                <code
                    style="word-break:break-all">{$internautenav_existing_customers_url|escape:'htmlall':'UTF-8'}</code>
                <br><small
                    class="text-muted">{$internautenav_existing_customers_url_help|escape:'htmlall':'UTF-8'}</small>
            </td>
        </tr>
    </table>

    <form method="post" action="{$internautenav_cleanup_action|escape:'htmlall':'UTF-8'}" style="margin-top:12px">
        <button type="submit" name="submitInternautenavCleanup" class="btn btn-warning">
            {$internautenav_cleanup_now_button|escape:'htmlall':'UTF-8'}
        </button>
        <span class="help-block" style="display:inline-block;margin-left:8px">
            {$internautenav_cleanup_now_help|escape:'htmlall':'UTF-8'}
        </span>
    </form>

    <form method="post" action="{$internautenav_cleanup_action|escape:'htmlall':'UTF-8'}" style="margin-top:8px"
        onsubmit="return confirm('{$internautenav_cleanup_pending_confirm|escape:'javascript':'UTF-8'}')">
        <button type="submit" name="submitInternautenavCleanupPending" class="btn btn-danger">
            {$internautenav_cleanup_pending_button|escape:'htmlall':'UTF-8'}
        </button>
        <span class="help-block" style="display:inline-block;margin-left:8px">
            {$internautenav_cleanup_pending_help|escape:'htmlall':'UTF-8'}
        </span>
    </form>
</div>