<div class="widget">
	<h3>{{$calendars_label}}</h3>
	{{foreach $calendars as $calendar}}
	<div id="calendar-{{$calendar.calendarid}}">
		<div class="ml-3{{if !$calendar@last}} form-group{{/if}}">
			<i id="calendar-btn-{{$calendar.calendarid}}" class="fa {{if $calendar.switch}}fa-calendar-check-o{{else}}fa-calendar-o{{/if}} generic-icons fakelink" onclick="add_remove_json_source('{{$calendar.json_source}}', '{{$calendar.color}}', {{$calendar.editable}})" style="color: {{$calendar.color}};"></i>{{$calendar.displayname}}
			<div class="float-end">
				<a href="#" onclick="exportDate(); return false;"><i id="download-icon" class="fa fa-cloud-download fakelink generic-icons-right"></i></a>
			</div>
		</div>
	</div>
	{{/foreach}}
</div>

{{if $my_calendars}}
<div class="widget">
	<h3>{{$my_calendars_label}}</h3>
	{{foreach $my_calendars as $calendar}}
	<div id="calendar-{{$calendar.calendarid}}">
		<div class="ml-3{{if !$calendar@last}} form-group{{/if}}">
			<i id="calendar-btn-{{$calendar.calendarid}}" class="fa {{if $calendar.switch}}fa-calendar-check-o{{else}}fa-calendar-o{{/if}} generic-icons fakelink" onclick="add_remove_json_source('{{$calendar.json_source}}', '{{$calendar.color}}', {{$calendar.editable}})" style="color: {{$calendar.color}};"></i>{{$calendar.displayname}}
			<div class="float-end">
				<i id="edit-icon" class="fa fa-pencil fakelink generic-icons" onclick="openClose('edit-calendar-{{$calendar.calendarid}}')"></i>
				<a href="/cdav/calendars/{{$calendar.ownernick}}/{{$calendar.uri}}/?export"><i id="download-icon" class="fa fa-cloud-download fakelink generic-icons"></i></a>
				<i id="share-icon" class="fa fa-share-alt fakelink generic-icons" onclick="openClose('share-calendar-{{$calendar.calendarid}}')"></i>
				<a href="#" onclick="var drop = dropItem('/cdav/calendar/drop/{{$calendar.calendarid}}/{{$calendar.instanceid}}', '#calendar-{{$calendar.calendarid}}'); if(drop) { add_remove_json_source('{{$calendar.json_source}}', '{{$calendar.color}}', {{$calendar.editable}}, 'drop'); } return false;"><i class="fa fa-trash-o drop-icons"></i></a>
			</div>
			<div id="share-calendar-{{$calendar.calendarid}}" class="sub-menu" style="display: none; border-color: {{$calendar.color}};">
				{{if $calendar.sharees}}
				{{foreach $calendar.sharees as $sharee}}
				<div id="sharee-{{$calendar.calendarid}}-{{$sharee@iteration}}" class="form-group">
					<i class="fa fa-share generic-icons"></i>{{$sharee.name}}&nbsp;{{$sharee.access}}
					<div class="float-end">
						<a href="#" onclick="dropItem('/cdav/calendar/dropsharee/{{$calendar.calendarid}}/{{$calendar.instanceid}}/{{$sharee.hash}}', '#sharee-{{$calendar.calendarid}}-{{$sharee@iteration}}'); return false;"><i class="fa fa-trash-o drop-icons"></i></a>
					</div>
				</div>
				{{/foreach}}
				{{/if}}
				<form method="post" action="">
					<label for="share-{{$calendar.calendarid}}">{{$share_label}}</label>
					<input name="calendarid" type="hidden" value="{{$calendar.calendarid}}">
					<input name="instanceid" type="hidden" value="{{$calendar.instanceid}}">
					<div class="form-group">
						<select id="share-{{$calendar.calendarid}}" name="sharee" class="form-control">
							{{$sharee_options}}
						</select>
					</div>
					<div class="form-group">
						<select name="access" class="form-control">
							{{$access_options}}
						</select>
					</div>
					<div class="form-group">
						<button type="submit" name="share" value="share" class="btn btn-primary btn-sm">{{$share}}</button>
					</div>
				</form>
			</div>
			<div id="edit-calendar-{{$calendar.calendarid}}" class="sub-menu" style="display: none; border-color: {{$calendar.color}};">
				<form id="edit-calendar-{{$calendar.calendarid}}" method="post" action="" class="colorpicker-component color-edit">
					<input id="id-{{$calendar.calendarid}}" name="id" type="hidden" value="{{$calendar.calendarid}}:{{$calendar.instanceid}}">
					<input id="color-{{$calendar.calendarid}}" name="color" type="hidden" value="{{$calendar.color}}" class="color-edit-input">
					<label for="edit-form-{{$calendar.calendarid}}">{{$edit_label}}</label>
					<div id="edit-form-{{$calendar.calendarid}}" class="input-group form-group">
						<input id="create-{{$calendar.calendarid}}" name="{DAV:}displayname" type="text" value="{{$calendar.displayname}}" class="form-control">
						<div class="input-group-append">
							<div class="input-group-addon p-3"></div>
						</div>
					</div>
					<div class="form-group">
						<button type="submit" name="edit" value="edit" class="btn btn-primary btn-sm">{{$edit}}</button>
					</div>
				</form>
			</div>
		</div>
	</div>
	{{/foreach}}
</div>
{{/if}}

{{if $shared_calendars}}
<div class="widget">
	<h3>{{$shared_calendars_label}}</h3>
	{{foreach $shared_calendars as $calendar}}
	<div id="shared-calendar-{{$calendar.calendarid}}" class="ml-3{{if !$calendar@last}} form-group{{/if}}">
		<i id="calendar-btn-{{$calendar.calendarid}}" class="fa {{if $calendar.switch}}{{if $calendar.access == 'read-write'}}fa-calendar-check-o{{else}}fa-calendar-times-o{{/if}}{{else}}fa-calendar-o{{/if}} generic-icons fakelink" onclick="add_remove_json_source('{{$calendar.json_source}}', '{{$calendar.color}}', {{$calendar.editable}}, {{if $calendar.access == 'read-write'}}'fa-calendar-check-o'{{else}}'fa-calendar-times-o'{{/if}})"  style="color: {{$calendar.color}};"></i>{{$calendar.displayname}} ({{$calendar.sharer}})
		<div class="float-end">
			<a href="/cdav/calendars/{{$calendar.ownernick}}/{{$calendar.uri}}/?export"><i id="download-icon" class="fa fa-cloud-download fakelink generic-icons"></i></a>
			<a href="#" onclick="var drop = dropItem('/cdav/calendar/drop/{{$calendar.calendarid}}/{{$calendar.instanceid}}', '#shared-calendar-{{$calendar.calendarid}}'); if(drop) { add_remove_json_source('{{$calendar.json_source}}', '{{$calendar.color}}', {{$calendar.editable}}, 'drop'); } return false;"><i class="fa fa-trash-o drop-icons"></i></a>
		</div>
	</div>
	{{/foreach}}
</div>
{{/if}}

<div class="widget">
	<h3>{{$tools_label}}</h3>
	<div class="nav nav-pills flex-column">
		<li class="nav-item">
			<a class="nav-link" href="#" onclick="openClose('create-calendar'); return false;"><i class="fa fa-calendar-plus-o generic-icons"></i> {{$create_label}}</a>
		</li>
		<div id="create-calendar" class="sub-menu-wrapper">
			<div class="sub-menu">
				<form method="post" action="" class="colorpicker-component color-edit">
					<input id="color" name="color" type="hidden" value="#ff8f00" class="color-edit-input">
					<div id="create-form" class="input-group form-group">
						<input id="create" name="{DAV:}displayname" type="text" placeholder="{{$create_placeholder}}" class="form-control">
						<div class="input-group-append">
							<div class="input-group-addon p-3"></div>
						</div>
					</div>
					<div class="form-group">
						<button type="submit" name="create" value="create" class="btn btn-primary btn-sm">{{$create}}</button>
					</div>
				</form>
			</div>
		</div>
		<li class="nav-item">
			<a class="nav-link" href="#" onclick="openClose('upload-form'); return false;"><i class="fa fa-cloud-upload generic-icons"></i> {{$import_label}}</a>
		</li>
		<div id="upload-form" class="sub-menu-wrapper">
			<div class="sub-menu">
				<form enctype="multipart/form-data" method="post" action="">
					<div class="form-group">
						<select id="import" name="target" class="form-control">
							<option value="">{{$import_placeholder}}</option>
							<optgroup label="{{$tools_options_label.0}}">
							<option value="{{$calendar.calendarid}}">{{$calendar.displayname}}</option>
							<optgroup label="{{$tools_options_label.1}}">
							{{foreach $writable_calendars as $writable_calendar}}
							<option value="{{$writable_calendar.id.0}}:{{$writable_calendar.id.1}}">{{$writable_calendar.displayname}}</option>
							{{/foreach}}
						</select>
					</div>
					<div class="form-group">
						<input class="form-control-file w-100" id="event-upload-choose" type="file" name="userfile" />
					</div>
					<button class="btn btn-primary btn-sm" type="submit" name="c_upload" value="c_upload">{{$upload}}</button>
				</form>
			</div>
		</div>
	</div>
</div>
