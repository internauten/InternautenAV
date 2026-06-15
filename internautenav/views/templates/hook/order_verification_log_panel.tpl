<!-- begin /modules:internautenav/views/templates/hook/order_verification_log_panel.tpl -->
<div class="card mt-2">
    <div class="card-header">
        <h3>{$internautenav_order_protocol_title|escape:'htmlall':'UTF-8'}</h3>
    </div>
    <div class="card-body">
        {if empty($internautenav_order_protocol_rows)}
            <p class="text-muted">{$internautenav_order_protocol_empty|escape:'htmlall':'UTF-8'}</p>
        {else}
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>{$internautenav_order_protocol_col_checked_at|escape:'htmlall':'UTF-8'}</th>
                            <th>{$internautenav_order_protocol_col_doc_type|escape:'htmlall':'UTF-8'}</th>
                            <th>{$internautenav_order_protocol_col_result|escape:'htmlall':'UTF-8'}</th>
                            <th>{$internautenav_order_protocol_col_message|escape:'htmlall':'UTF-8'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$internautenav_order_protocol_rows item=row}
                            <tr>
                                <td>{$row.checked_at|escape:'htmlall':'UTF-8'}</td>
                                <td>{$row.doc_type|escape:'htmlall':'UTF-8'}</td>
                                <td>
                                    {if $row.is_ok}
                                        &#10003; {$internautenav_order_protocol_ok|escape:'htmlall':'UTF-8'}
                                    {else}
                                        &#10007; {$internautenav_order_protocol_fail|escape:'htmlall':'UTF-8'}
                                    {/if}
                                </td>
                                <td>{$row.message|escape:'htmlall':'UTF-8'}</td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        {/if}
    </div>
</div>
<!-- end /modules:internautenav/views/templates/hook/order_verification_log_panel.tpl -->