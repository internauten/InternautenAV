{extends file='customer/page.tpl'}

{block name='page_title'}
  {$internautenav_protocol_title|escape:'htmlall':'UTF-8'}
{/block}

{block name='page_content'}
  <p>{$internautenav_protocol_intro|escape:'htmlall':'UTF-8'}</p>

  {if $internautenav_protocol_rows|@count > 0}
    <table class="table table-striped table-bordered hidden-sm-down">
      <thead class="thead-default">
        <tr>
          <th>{$internautenav_protocol_timestamp|escape:'htmlall':'UTF-8'}</th>
          <th>{$internautenav_protocol_cart|escape:'htmlall':'UTF-8'}</th>
          <th>{$internautenav_protocol_doc|escape:'htmlall':'UTF-8'}</th>
          <th>{$internautenav_protocol_result|escape:'htmlall':'UTF-8'}</th>
          <th>{$internautenav_protocol_message|escape:'htmlall':'UTF-8'}</th>
        </tr>
      </thead>
      <tbody>
        {foreach from=$internautenav_protocol_rows item=row}
          <tr>
            <td>{$row.checked_at|escape:'htmlall':'UTF-8'}</td>
            <td>{if $row.cart_id > 0}{$row.cart_id|escape:'htmlall':'UTF-8'}{else}-{/if}</td>
            <td>{$row.doc_type|escape:'htmlall':'UTF-8'}</td>
            <td class="{$row.result_class|escape:'htmlall':'UTF-8'}"><strong>{$row.result_label|escape:'htmlall':'UTF-8'}</strong></td>
            <td>{$row.message|escape:'htmlall':'UTF-8'}</td>
          </tr>
        {/foreach}
      </tbody>
    </table>

    <div class="hidden-md-up">
      {foreach from=$internautenav_protocol_rows item=row}
        <div class="order" style="padding:0.75rem 0;border-bottom:1px solid #eee">
          <div class="row">
            <div class="col-xs-12">
              <div class="date text-muted">{$row.checked_at|escape:'htmlall':'UTF-8'}</div>
              <div>{$internautenav_protocol_doc|escape:'htmlall':'UTF-8'}: <strong>{$row.doc_type|escape:'htmlall':'UTF-8'}</strong></div>
              <div class="{$row.result_class|escape:'htmlall':'UTF-8'}"><strong>{$row.result_label|escape:'htmlall':'UTF-8'}</strong></div>
              {if $row.message}
                <div class="text-muted" style="font-size:0.875em">{$row.message|escape:'htmlall':'UTF-8'}</div>
              {/if}
            </div>
          </div>
        </div>
      {/foreach}
    </div>
  {else}
    <div class="alert alert-info" role="alert" data-alert="info">
      {$internautenav_protocol_empty|escape:'htmlall':'UTF-8'}
    </div>
  {/if}
{/block}