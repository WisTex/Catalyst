<div id="thread-wrapper-{{$item.id}}" class="thread-wrapper{{if $item.toplevel}} {{$item.toplevel}} clearfix generic-content-wrapper{{/if}}">
	<a name="{{$item.id}}" ></a>
	<div class="clearfix wall-item-outside-wrapper {{$item.indent}}{{$item.previewing}}{{if $item.owner_url}} wallwall{{/if}}" id="wall-item-outside-wrapper-{{$item.id}}" >
		<div class="wall-item-content-wrapper {{$item.indent}}" id="wall-item-content-wrapper-{{$item.id}}">
			{{if $item.photo}}
			<div class="wall-photo-item" id="wall-photo-item-{{$item.id}}">
				{{$item.photo}}
			</div>
			{{/if}}
			{{if $item.event}}
			<div class="wall-event-item" id="wall-event-item-{{$item.id}}">
				{{$item.event}}
			</div>
			{{/if}}
			{{if $item.title && !$item.event}}
			<div class="p-2{{if $item.is_new}} bg-primary text-white{{/if}} wall-item-title h3{{if !$item.photo}} rounded-top{{/if}}" id="wall-item-title-{{$item.id}}">
				{{if $item.title_tosource}}{{if $item.plink}}<a href="{{$item.plink.href}}" title="{{$item.title}} ({{$item.plink.title}})">{{/if}}{{/if}}{{$item.title}}{{if $item.title_tosource}}{{if $item.plink}}</a>{{/if}}{{/if}}
			</div>
			{{if ! $item.is_new}}
			<hr class="m-0">
			{{/if}}
			{{/if}}
			<div class="p-2 clearfix wall-item-head{{if $item.is_new && !$item.title && !$item.event && !$item.is_comment}} wall-item-head-new rounded-top{{/if}}">
				{{if $item.pinned}}
				    <span class="float-right wall-item-pinned" title="{{$item.pinned}}" id="wall-item-pinned-{{$item.id}}"><i class="fa fa-thumb-tack">&nbsp;</i></span>
				{{/if}}
				<div class="wall-item-info" id="wall-item-info-{{$item.id}}" >
					<div class="wall-item-photo-wrapper{{if $item.owner_url}} wwfrom{{/if}}" id="wall-item-photo-wrapper-{{$item.id}}">
						<img src="{{$item.thumb}}" class="fakelink wall-item-photo{{$item.sparkle}} u-photo p-name" id="wall-item-photo-{{$item.id}}" alt="{{$item.name}}" data-toggle="dropdown" />
						{{if $item.thread_author_menu}}
						<i class="fa fa-caret-down wall-item-photo-caret cursor-pointer" data-toggle="dropdown"></i>
						<div class="dropdown-menu">
							{{foreach $item.thread_author_menu as $mitem}}
							<a class="dropdown-item" {{if $mitem.href}}href="{{$mitem.href}}"{{/if}} {{if $mitem.action}}onclick="{{$mitem.action}}"{{/if}} {{if $mitem.title}}title="{{$mitem.title}}"{{/if}} >{{$mitem.title}}</a>
							{{/foreach}}
						</div>
						{{/if}}
					</div>
				</div>
				<div class="wall-item-lock dropdown">
					<i class="fa {{if $item.locktype == 2}}fa-envelope{{elseif $item.locktype == 1}}fa-lock dimmer{{else}}fa-globe dimmer{{/if}} lockview{{if $item.privacy_warning}} text-warning{{/if}}" data-toggle="dropdown" title="{{$item.lock}}" onclick="lockview('item',{{$item.id}});" ></i>&nbsp;
					<div id="panel-{{$item.id}}" class="dropdown-menu"></div>
				</div>
				<div class="wall-item-author">
					{{if $item.previewing}}<span class="preview-indicator"><i class="fa fa-eye" title="{{$item.preview_lbl}}"></i></span>&nbsp;{{/if}}
					<a href="{{$item.profile_url}}" title="{{$item.linktitle}}" class="wall-item-name-link"><span class="wall-item-name{{$item.sparkle}}" id="wall-item-name-{{$item.id}}" >{{$item.name}}</span></a>{{if $item.owner_url}}&nbsp;{{$item.via}}&nbsp;<a href="{{$item.owner_url}}" title="{{$item.olinktitle}}" class="wall-item-name-link"><span class="wall-item-name{{$item.osparkle}}" id="wall-item-ownername-{{$item.id}}">{{$item.owner_name}}</span></a>{{/if}}
				</div>
				<div class="wall-item-ago"  id="wall-item-ago-{{$item.id}}">
					{{if $item.location}}<span class="wall-item-location" id="wall-item-location-{{$item.id}}">{{$item.location}},&nbsp;</span>{{/if}}<span class="autotime" title="{{$item.isotime}}">{{$item.localtime}}{{if $item.editedtime}}&nbsp;{{$item.editedtime}}{{/if}}{{if $item.expiretime}}&nbsp;{{$item.expiretime}}{{/if}}</span>{{if $item.editedtime}}&nbsp;<i class="fa fa-pencil"></i>{{/if}}&nbsp;{{if $item.app}}<span class="item.app">{{$item.str_app}}</span>{{/if}}
				</div>
			</div>
			{{if $item.divider}}
			<hr class="wall-item-divider">
			{{/if}}
			{{if $item.body}}
			<div class="p-2 clrearfix {{if $item.is_photo}} wall-photo-item{{else}} wall-item-content{{/if}}" id="wall-item-content-{{$item.id}}">
				<div class="wall-item-body" id="wall-item-body-{{$item.id}}" >
					{{$item.body}}
				</div>
			</div>
			{{/if}}
			{{if $item.has_tags}}
			<div class="p-2 wall-item-tools clearfix">
				<div class="body-tags">
					<span class="tag">{{$item.mentions}} {{$item.tags}} {{$item.categories}} {{$item.folders}}</span>
				</div>
			</div>
			{{/if}}
			<div class="p-2 clearfix wall-item-tools">
				<div class="float-right wall-item-tools-right">
					<div class="btn-group">
						<div id="like-rotator-{{$item.id}}" class="spinner-wrapper">
							<div class="spinner s"></div>
						</div>
					</div>
					<div class="btn-group">
						<button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-toggle="dropdown">
							<i class="fa fa-cog"></i>
						</button>
						<div class="dropdown-menu dropdown-menu-right">
							{{if $item.conv}}
							<a class="dropdown-item" href='{{$item.conv.href}}' id='context-{{$item.id}}' title='{{$item.conv.title}}'><i class="fa fa-fw fa-list generic-icons-nav"></i>{{$item.conv.title}}</a>
							{{/if}}
							{{if $item.star}}
							<a class="dropdown-item" href="#" onclick="dostar({{$item.id}}); return false;"><i id="starred-{{$item.id}}" class="fa fa-fw{{if $item.star.isstarred}} starred fa-star{{else}} unstarred fa-star-o{{/if}} generic-icons-nav" title="{{$item.star.toggle}}"></i>{{$item.star.toggle}}</a>
							{{/if}}
							{{if $item.thread_action_menu}}
							{{foreach $item.thread_action_menu as $mitem}}
							<a class="dropdown-item" {{if $mitem.href}}href="{{$mitem.href}}"{{/if}} {{if $mitem.action}}onclick="{{$mitem.action}}"{{/if}} {{if $mitem.title}}title="{{$mitem.title}}"{{/if}} ><i class="fa fa-fw fa-{{$mitem.icon}} generic-icons-nav"></i>{{$mitem.title}}</a></li>
							{{/foreach}}
							{{/if}}
							{{if $item.drop.dropping}}
							<a class="dropdown-item" href="item/drop/{{$item.id}}" onclick="return confirmDelete();" title="{{$item.drop.delete}}" ><i class="fa fa-fw fa-trash-o generic-icons-nav"></i>{{$item.drop.delete}}</a></li>
							{{/if}}
						</div>
					</div>
				</div>
				{{if $item.star && $item.star.isstarred}}
				<div class="btn-group" id="star-button-{{$item.id}}">
					<button type="button" class="btn btn-outline-secondary btn-sm wall-item-like" onclick="dostar({{$item.id}});"><i class="fa fa-star"></i></button>
				</div>
				{{/if}}
				{{if $item.attachments}}
				<div class="wall-item-tools-left btn-group">
					<button type="button" class="btn btn-outline-secondary btn-sm wall-item-like dropdown-toggle" data-toggle="dropdown" id="attachment-menu-{{$item.id}}"><i class="fa fa-paperclip"></i></button>
					<div class="dropdown-menu">{{$item.attachments}}</div>
				</div>
				{{/if}}

				<div class="wall-item-tools-left btn-group" id="wall-item-tools-left-{{$item.id}}">
					{{if $item.mode === 'moderate'}}
					<a href="moderate/{{$item.id}}/approve" class="btn btn-success btn-sm">{{$item.approve}}</a>
					<a href="moderate/{{$item.id}}/drop" class="btn btn-danger btn-sm">{{$item.delete}}</a>
					{{/if}}
				</div>

			</div>
		</div>
	</div>
</div>

