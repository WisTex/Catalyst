<div class="widget">
	<h3 class="d-flex justify-content-between align-items-center">
		{{$title}}
		{{if $reset}}
		<a href="{{$reset.url}}" class="text-muted" title="{{$reset.title}}">
			<i class="fa fa-fw fa-{{$reset.icon}}"></i>
		</a>
		{{/if}}
	</h3>
	{{$content}}
	{{if $name}}
	<div class="notifications-textinput">
		<form method="get" action="{{$name.url}}" role="search">
			<div class="text-muted notifications-textinput-filter"><i class="fa fa-fw fa-users"></i></div>
			<input id="xchan" type="hidden" value="" name="xchan" />
			<input id="xchan-filter" class="form-control form-control-sm{{if $name.sel}} {{$name.sel}}{{/if}}" autocomplete="off" autofill="off" type="text" value="" placeholder="{{$name.label}}" name="name" title="" />
		</form>
	</div>
	<script>
		$("#xchan-filter").name_autocomplete(baseurl + '/acl', 'x', true, function(data) {
			$("#xchan").val(data.xchan);
		});
	</script>
	{{/if}}
</div>
