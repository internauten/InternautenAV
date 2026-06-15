<!-- begin /modules:internautenav/views/templates/hook/admin_customers_protocol.tpl -->
<div id="{$internautenav_badge_id|escape:'htmlall':'UTF-8'}" style="margin-bottom:8px">
    {$internautenav_status_badge_html nofilter}</div>

<script>
    (function() {
            function inavMoveBadge() {
                var badge = document.getElementById("{$internautenav_badge_id|escape:'javascript':'UTF-8'}");
                if (!badge) {
                    return;
                }

                var contentSelectors = ["#content", "#main", "main", "#main-div", ".page-body", "body"];
                var content = null;
                for (var i = 0; i < contentSelectors.length; i++) {
                    content = document.querySelector(contentSelectors[i]);
                    if (content) {
                        break;
                    }
                }

                var cards = (content || document.body).querySelectorAll(".card");
                for (var c = 0; c < cards.length; c++) {
                    var card = cards[c];
                    if (card.id === "{$internautenav_protocol_card_id|escape:'javascript':'UTF-8'}") {
                    continue;
                }
                if (card.contains(badge)) {
                    continue;
                }

                var body = card.querySelector(".card-body");
                if (body) {
                    body.insertBefore(badge, body.firstChild);
                    return;
                }
            }
        }

        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", inavMoveBadge);
        } else {
            inavMoveBadge();
        }
    })();
</script>

<div class="col">
    <div class="card mt-2" id="{$internautenav_protocol_card_id|escape:'htmlall':'UTF-8'}">
        <h3 class="card-header">{$internautenav_protocol_title|escape:'htmlall':'UTF-8'}</h3>
        <div class="card-body">
            {if empty($internautenav_protocol_rows)}
                <p class="text-muted mb-0">{$internautenav_protocol_empty|escape:'htmlall':'UTF-8'}</p>
            {else}
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>{$internautenav_protocol_col_checked_at|escape:'htmlall':'UTF-8'}</th>
                                <th>{$internautenav_protocol_col_id_cart|escape:'htmlall':'UTF-8'}</th>
                                <th>{$internautenav_protocol_col_doc_type|escape:'htmlall':'UTF-8'}</th>
                                <th>{$internautenav_protocol_col_result|escape:'htmlall':'UTF-8'}</th>
                                <th>{$internautenav_protocol_col_message|escape:'htmlall':'UTF-8'}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach from=$internautenav_protocol_rows item=row}
                                <tr>
                                    <td>{$row.checked_at|escape:'htmlall':'UTF-8'}</td>
                                    <td>{$row.id_cart|escape:'htmlall':'UTF-8'}</td>
                                    <td>{$row.doc_type|escape:'htmlall':'UTF-8'}</td>
                                    <td>
                                        {if $row.is_ok}
                                            &#10003; {$internautenav_protocol_ok|escape:'htmlall':'UTF-8'}
                                        {else}
                                            &#10007; {$internautenav_protocol_fail|escape:'htmlall':'UTF-8'}
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
</div>
<!-- end /modules:internautenav/views/templates/hook/admin_customers_protocol.tpl -->