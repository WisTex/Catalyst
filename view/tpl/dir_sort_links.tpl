<div class="widget" id="dir_sort_links">
<h3>{{$header}}</h3>

{{if ! $hide_local}}
{{include file="field_checkbox.tpl" field=$globaldir}}
{{/if}}
{{include file="field_checkbox.tpl" field=$pubforums}}
{{include file="field_checkbox.tpl" field=$safemode}}
{{if $covers}}
{{include file="field_checkbox.tpl" field=$covers}}
{{/if}}
{{include file="field_checkbox.tpl" field=$activedir}}

</div>
