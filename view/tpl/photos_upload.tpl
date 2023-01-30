<div id="photo-upload-form">
	<input id="invisible-photos-file-upload" type="file" name="files" style="visibility:hidden;position:absolute;top:-50;left:-50;width:0;height:0;" data-nickname='{{$nickname}}' multiple >
	<div class="section-content-tools-wrapper">
		<form action="#" enctype="multipart/form-data" method="post" name="photos-upload-form" id="photos-upload-form" class="acl-form" data-form_id="photos-upload-form" data-allow_cid='{{$allow_cid}}' data-allow_gid='{{$allow_gid}}' data-deny_cid='{{$deny_cid}}' data-deny_gid='{{$deny_gid}}'>
			<input type="hidden" id="photos-upload-source" name="source" value="photos" />

			<div class="form-group">
				<label for="photos-upload-album">{{$newalbum_label}}</label>
				<input type="text" class="form-control" id="photos-upload-album" name="newalbum" placeholder="{{$newalbum_placeholder}}" value="{{$selname}}" list="dl-photo-upload">
				<datalist id="dl-photo-upload">
				{{foreach $albums as $al}}
					{{if $al.text}}
					<option value="{{$al.text}}" />
					{{/if}}
				{{/foreach}}
				</datalist>
			</div>
			{{if $default}}
			{{include file="field_input.tpl" field=$caption}}
			{{include file="field_input.tpl" field=$title}}
            <input type="hidden" name="visible" value="0" />
			<div class="float-end btn-group">
				<div class="btn-group">
					{{if $lockstate}}
					<button id="dbtn-acl" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#aclModal" onclick="return false;">
						<i id="jot-perms-icon" class="fa fa-{{$lockstate}}"></i>
					</button>
					{{/if}}
					<button id="dbtn-submit" class="btn btn-primary btn-sm">{{$submit}}</button>
				</div>

			</div>
			{{/if}}
			<div class="clear"></div>

			{{if $uploader}}
			{{include file="field_input.tpl" field=$caption}}
			{{include file="field_input.tpl" field=$title}}
			<input type="hidden" name="visible" value="0" />
			<div id="photos-upload-perms" class="btn-group float-end">
				{{if $lockstate}}
				<button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#aclModal" onclick="return false;">
					<i id="jot-perms-icon" class="fa fa-{{$lockstate}}"></i>
				</button>
				{{/if}}
				<div class="float-end">
					{{$uploader}}
				</div>
			</div>
			{{/if}}
		</form>
	</div>
	<table id="upload-index">
		<tr id="new-upload-progress-bar-1"></tr> {{* this is needed to append the upload files in the right order *}}
	</table>
	{{$aclselect}}
	<div id="photos-upload-end" class="clear"></div>
</div>
