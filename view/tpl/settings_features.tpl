<div class="generic-content-wrapper">
	<div class="section-title-wrapper">
		<h2>{{$title}}</h2>
	</div>
	<form action="settings/features" method="post" autocomplete="off">
	<input type='hidden' name='form_security_token' value='{{$form_security_token}}'>
	{{if $hiddens}}
		{{foreach $hiddens as $k => $v}}
			<input type="hidden" name="feature_{{$k}}" value="{{$v}}" />
		{{/foreach}}
	{{/if}}
	<div class="panel-group" id="settings" role="tablist" aria-multiselectable="true">
		{{foreach $features as $g => $f}}
		<div class="panel">
			<div class="section-subtitle-wrapper" role="tab" id="{{$g}}-settings-title">
				<h3>
					<a data-toggle="collapse" data-target="#{{$g}}-settings-content" href="#" aria-expanded="true" aria-controls="{{$g}}-settings-collapse">
						{{$f.0}}
					</a>
				</h3>
			</div>
			<div id="{{$g}}-settings-content" class="collapse{{if $g == 'general'}} show{{/if}}" role="tabpanel" aria-labelledby="{{$g}}-settings-title" data-parent="#settings">
				<div class="section-content-tools-wrapper">
					{{foreach $f.1 as $fcat}}
						{{include file="field_checkbox.tpl" field=$fcat}}
					{{/foreach}}
					<div class="settings-submit-wrapper" >
						<button type="submit" name="submit" class="btn btn-primary">{{$submit}}</button>
					</div>
				</div>
			</div>
		</div>
		{{/foreach}}
	</div>
</div>
