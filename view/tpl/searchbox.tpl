<form action="{{$action_url}}" method="get" >
	<input type="hidden" name="f" value="" />
	<div id="{{$id}}" class="input-group">
		<input class="form-control" type="text" name="search" id="search-text" value="{{$s}}" onclick="this.submit();" />
		<div class="input-group-append">
			<button type="submit" name="submit" class="btn btn-outline-secondary" id="search-submit" value="{{$search_label}}"><i class="fa fa-search"></i></button>
			{{if $savedsearch}}
			<button type="submit" name="searchsave" class="btn btn-outline-secondary" id="search-save" value="{{$save_label}}"><i class="fa fa-floppy-o"></i></button>
			{{/if}}
		</div>
	</div>
</form>
