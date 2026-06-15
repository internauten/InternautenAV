<div
    style="margin:8px 0 4px;padding:10px 14px;border:1px solid {$internautenav_badge_border|escape:'htmlall':'UTF-8'};border-radius:4px;background:{$internautenav_badge_bg|escape:'htmlall':'UTF-8'};color:{$internautenav_badge_color|escape:'htmlall':'UTF-8'};font-size:13px">
    <strong>{$internautenav_badge_label}</strong>
    {if $internautenav_badge_detail !== ''}
        &nbsp;<span style="font-weight:normal;font-size:12px">{$internautenav_badge_detail}</span>
    {/if}
</div>