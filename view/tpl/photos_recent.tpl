<div class="generic-content-wrapper">
	<div class="section-title-wrapper">
		<div class="float-end">
			<a href="{{$files_path}}" title="{{$file_view}}"><i class="fa fa-folder btn btn-outline-secondary btn-sm" title="{{$file_view}}"></i></a>
			{{if $can_post}}
			<button class="btn btn-sm btn-success acl-form-trigger" title="{{$usage}}" onclick="openClose('photo-upload-form');" data-form_id="photos-upload-form"><i class="fa fa-plus-circle"></i>&nbsp;{{$upload}}</button>
			{{/if}}
		</div>
		<h2>{{$title}}</h2>
		<div class="clear"></div>
	</div>
	{{$upload_form}}
	<div class="section-content-wrapper-np">
		<div id="photo-album-contents-{{$album_id}}" style="display: none">
			{{foreach $photos as $photo}}
				{{include file="photo_top.tpl"}}
			{{/foreach}}
			<div id="page-end"></div>
		</div>
	</div>
</div>
<div class="photos-end"></div>
<div id="page-spinner" class="spinner-wrapper">
	<div class="spinner m"></div>
</div>
<script>
$(document).ready(function() {
	loadingPage = false;
	justifyPhotos('photo-album-contents-{{$album_id}}');
});
</script>
