<div class="generic-content-wrapper">
	<div class="section-title-wrapper">
		<script>
			function primehub(id) {
				$.post(baseurl + '/locs','primary='+id,function(data) { window.location.href=window.location.href; });
			}
			function drophub(id) {
				$.post(baseurl + '/locs','drop='+id,function(data) { window.location.href=window.location.href; });
			}
		</script>
		<button class="btn btn-success btn-sm float-end" onclick="window.location.href='/locs/f=&sync=1'; return false;"><i class="fa fa-refresh"></i>&nbsp;{{$sync}}</button>
		<h2>{{$header}}</h2>
	</div>
	<div class="section-content-wrapper-np">
		<div class="section-content-warning-wrapper">
			{{$sync_text}}
		</div>
		<div class="section-content-info-wrapper">
			{{$drop_text}}<br>
			{{$last_resort}}
		</div>
		<table id="locs-index">
			<tr>
				<th>{{$addr}}</th>
				<th class="d-none d-md-table-cell">{{$loc}}</th>
				<th>{{$mkprm}}</th>
				<th>{{$drop}}</th>
			</tr>
			{{foreach $hubs as $hub}}
			{{if ! $hub.hubloc_deleted }}
			<tr class="locs-index-row">
				<td>{{$hub.hubloc_addr}}</td>
				<td class="d-none d-md-table-cell">{{$hub.hubloc_url}}</td>
				<td>{{if $hub.hubloc_primary}}<i class="fa fa-check-square-o"></i>{{else}}<i class="fa fa-square-o primehub" onclick="primehub({{$hub.hubloc_id}}); return false;"></i>{{/if}}</td>
				<td>{{if $hub.hubloc_url != $base_url}}<i class="fa fa-trash-o drophub" onclick="drophub({{$hub.hubloc_id}}); return false;"></i></td>{{/if}}
			</tr>
			{{/if}}
			{{/foreach}}
		</table>
	</div>
</div>
