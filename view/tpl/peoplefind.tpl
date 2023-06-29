<div id="peoplefind-sidebar" class="widget">
	<h3>{{$findpeople}}</h3>
	<form action="directory" method="post">
		<div class="input-group">
			<input class="form-control" type="text" name="search" title="{{$hint}}{{if $advanced_search}}{{$advanced_hint}}{{/if}}" placeholder="{{$desc}}" />
			<div class="input-group-append">
				<button class="btn btn-outline-secondary" type="submit" name="submit"><i class="fa fa-fw fa-search"></i></button>
			</div>
		</div>
	</form>
	<ul class="nav nav-pills flex-column">
		{{if $similar}}<li class="nav-item"><a class="nav-link" href="match" >{{$similar}}</a></li>{{/if}}
		{{if $loggedin}}<li class="nav-item"><a class="nav-link" href="directory?f=&suggest=1" >{{$suggest}}</a></li>{{/if}}
		{{if $sites}}<li class="nav-item"><a class="nav-link" href="communities" >{{$sites}}</a></li>{{/if}}
		<li class="nav-item"><a class="nav-link" href="randprof" >{{$random}}</a></li>
		{{if $loggedin}}{{if $inv}}<li class="nav-item"><a class="nav-link" href="invite" >{{$inv}}</a></li>{{/if}}{{/if}}
	</ul>
</div>
