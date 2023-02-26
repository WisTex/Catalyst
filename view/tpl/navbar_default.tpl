{{if $nav.login && !$userinfo}}
<div class="d-lg-none pt-1 pb-1">
	{{if $nav.loginmenu.1.4}}
	<a class="btn btn-primary btn-sm text-white" href="#" title="{{$nav.loginmenu.1.3}}" id="{{$nav.loginmenu.1.4}}_collapse" data-bs-toggle="modal" data-bs-target="#nav-login">
		{{$nav.loginmenu.1.1}}
	</a>
	{{else}}
	<a class="btn btn-primary btn-sm text-white" href="login" title="{{$nav.loginmenu.1.3}}">
		{{$nav.loginmenu.1.1}}
	</a>
	{{/if}}
	{{if $nav.register}}
	<a class="btn btn-warning btn-sm text-dark" href="{{$nav.register.0}}" title="{{$nav.register.3}}" id="{{$nav.register.4}}" >
		{{$nav.register.1}}
	</a>
	{{/if}}
</div>
{{/if}}
{{if $userinfo}}
<div class="dropdown">
	<div class="fakelink usermenu" data-bs-toggle="dropdown">
		<img id="avatar" src="{{$userinfo.icon}}" alt="{{$userinfo.name}}">
		<i class="fa fa-caret-down"></i>
	</div>
	{{if $is_owner}}
	<div class="dropdown-menu">
		{{foreach $nav.usermenu as $usermenu}}
		<a class="dropdown-item{{if $usermenu.2}} active{{/if}}"  href="{{$usermenu.0}}" title="{{$usermenu.3}}" role="menuitem" id="{{$usermenu.4}}">{{$usermenu.1}}</a>
		{{/foreach}}
		{{if $nav.manage}}
		<a class="dropdown-item{{if $sel.name == Manage}} active{{/if}}" href="{{$nav.manage.0}}" title="{{$nav.manage.3}}" role="menuitem" id="{{$nav.manage.4}}">{{$nav.manage.1}}</a>
		{{/if}}	
		{{if $nav.channels}}
		{{foreach $nav.channels as $chan}}
		<a class="dropdown-item" href="manage/{{$chan.channel_id}}" title="{{$chan.channel_name}}" role="menuitem"><i class="fa fa-circle{{if $localuser == $chan.channel_id}} text-success{{else}} invisible{{/if}}"></i> {{if $chan.channel_system}}<strong>{{$chan.channel_name}}</strong>{{else}}{{$chan.channel_name}}{{/if}}</a>
		{{/foreach}}
		{{/if}}
		{{if $nav.profiles}}
		<a class="dropdown-item" href="{{$nav.profiles.0}}" title="{{$nav.profiles.3}}" role="menuitem" id="{{$nav.profiles.4}}">{{$nav.profiles.1}}</a>
		{{/if}}
		{{if $nav.safe}}
		<div class="dropdown-divider"></div>
		<a class="dropdown-item{{if $sel.name == Safe}} active{{/if}}" href="{{$nav.safe.0}}" title="{{$nav.safe.3}}" role="menuitem" id="{{$nav.safe.4}}">{{$nav.safe.1}} {{$nav.safe.2}}</a>
		{{/if}}
		{{if $nav.logout}}
		<div class="dropdown-divider"></div>
		<a class="dropdown-item" href="{{$nav.logout.0}}" title="{{$nav.logout.3}}" role="menuitem" id="{{$nav.logout.4}}">{{$nav.logout.1}}</a>
		{{/if}}
	</div>
	{{/if}}
	{{if ! $is_owner}}
	<div class="dropdown-menu" role="menu" aria-labelledby="avatar">
		<a class="dropdown-item" href="{{$nav.rusermenu.0}}" role="menuitem"><i class="fa fa-fw fa-home"></i> {{$nav.rusermenu.1}}</a>
		<a class="dropdown-item" href="{{$nav.rusermenu.2}}" role="menuitem"><i class="fa fa-fw fa-window-close-o"></i> {{$nav.rusermenu.3}}</a>
	</div>
	{{/if}}
</div>
{{if $sel.name}}
<div id="nav-app-link-wrapper" class="navbar-nav mr-auto">
	<a id="nav-app-link" href="{{$url}}" class="nav-link text-truncate">
		{{$sel.name}}
		{{if $sitelocation}}
		<br><small>{{$sitelocation}}</small>
		{{/if}}
	</a>
</div>
{{/if}}
{{/if}}
<div class="navbar-toggler-right">
	<button id="expand-aside" type="button" class="d-lg-none navbar-toggler border-0" title="{{$asidetitle}}">
		<i class="fa fa-arrow-circle-right" id="expand-aside-icon"></i>
	</button>
	{{if $localuser || $nav.pubs}}
	<button id="notifications-btn-1" type="button" class="navbar-toggler border-0 notifications-btn" title="{{$notificationstitle}}">
		<i id="notifications-btn-icon-1" class="fa fa-exclamation-circle notifications-btn-icon"></i>
	</button>
	{{/if}}
	<button id="menu-btn" class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-collapse-2" title="{{$appstitle}}">
		<i class="fa fa-bars"></i>
	</button>
</div>
<div class="collapse navbar-collapse flex-row-reverse" id="navbar-collapse-1">
	<ul class="navbar-nav mr-auto">
		{{if $nav.login && !$userinfo}}
		<li class="nav-item d-lg-flex">
			{{if $nav.loginmenu.1.4}}
			<a class="nav-link" href="#" title="{{$nav.loginmenu.1.3}}" id="{{$nav.loginmenu.1.4}}" data-bs-toggle="modal" data-bs-target="#nav-login">
			{{$nav.loginmenu.1.1}}
			</a>
			{{else}}
			<a class="nav-link" href="login" title="{{$nav.loginmenu.1.3}}">
				{{$nav.loginmenu.1.1}}
			</a>
			{{/if}}
		</li>
		{{/if}}
		{{if $nav.register}}
		<li class="nav-item {{$nav.register.2}} d-lg-flex">
			<a class="nav-link" href="{{$nav.register.0}}" title="{{$nav.register.3}}" id="{{$nav.register.4}}">{{$nav.register.1}}</a>
		</li>
		{{/if}}
		{{if $nav.alogout}}
		<li class="nav-item {{$nav.alogout.2}} d-lg-flex">
			<a class="nav-link" href="{{$nav.alogout.0}}" title="{{$nav.alogout.3}}" id="{{$nav.alogout.4}}">{{$nav.alogout.1}}</a>
		</li>
		{{/if}}
	</ul>

	<div id="banner" class="navbar-text">{{$banner}}</div>

	<ul id="nav-right" class="navbar-nav ml-auto">
		<li class="nav-item collapse clearfix" id="nav-search">
			<form class="form-inline" method="get" action="{{$nav.search.4}}" role="search">
				<input class="form-control form-control-sm mt-1 mr-2" id="nav-search-text" type="text" value="" placeholder="{{$help}}" name="search" title="{{$nav.search.3}}" onclick="this.submit();" onblur="closeMenu('nav-search'); openMenu('nav-search-btn');"/>
			</form>
			<div id="nav-search-spinner" class="spinner-wrapper">
				<div class="spinner s"></div>
			</div>
		</li>
		<li class="nav-item" id="nav-search-btn">
			<a class="nav-link" href="#nav-search" title="{{$nav.search.3}}" onclick="openMenu('nav-search'); closeMenu('nav-search-btn'); $('#nav-search-text').focus(); return false;"><i class="fa fa-fw fa-search"></i></a>
		</li>
		{{if $localuser || $nav.pubs}}
		<li id="notifications-btn" class="nav-item d-xl-none">
			<a class="nav-link text-white notifications-btn" href="#" title="{{$notificationstitle}}"><i id="notifications-btn-icon" class="fa fa-exclamation-circle  notifications-btn-icon"></i></a>
		</li>
		{{/if}}
		{{if $channel_menu && $channel_apps.0}}
		<li class="nav-item dropdown" id="channel-menu">
			<a class="nav-link" href="#" data-bs-toggle="dropdown"><img src="{{$channel_thumb}}" style="height:14px; width:14px;position:relative; top:-2px;" /></a>
			<div id="dropdown-menu" class="dropdown-menu dropdown-menu-right">
				{{foreach $channel_apps as $channel_app}}
				{{$channel_app}}
				{{/foreach}}
			</div>
		</li>
		{{/if}}
		{{if $navbar_apps}}
		{{foreach $navbar_apps as $navbar_app}}
		<li>
		{{$navbar_app}}
		</li>
		{{/foreach}}
		{{/if}}
		<li class="nav-item dropdown" id="app-menu">
			<a class="nav-link" href="#" data-bs-toggle="dropdown" title="{{$appstitle}}"><i class="fa fa-fw fa-bars"></i></a>
			<div id="dropdown-menu" class="dropdown-menu dropdown-menu-right">
				{{if $channel_apps.0 && ! $channel_menu}}
				{{foreach $channel_apps as $channel_app}}
				{{$channel_app}}
				{{/foreach}}
				<div class="dropdown-divider"></div>
				<div class="dropdown-header text-black-50 sys-apps-toggle" onclick="$('#dropdown-menu').click(function(e) { e.stopPropagation(); }); openClose('sys_apps');">
					{{$sysapps_toggle}}
				</div>
				<div id="sys_apps" style="display:none;">
				{{/if}}
				{{foreach $nav_apps as $nav_app}}
				{{$nav_app}}
				{{/foreach}}
				{{if $channel_apps.0 && ! $channel_menu}}
				</div>
				{{/if}}
				{{if $is_owner}}
				<div class="dropdown-divider"></div>
				<a class="dropdown-item" href="/apps"><i class="generic-icons-nav fa fa-fw fa-asterisk"></i>{{$manageapps}}</a>
				<a class="dropdown-item" href="/apps/available"><i class="generic-icons-nav fa fa-fw fa-plus-circle"></i>{{$addapps}}</a>
				<a class="dropdown-item" href="/apporder"><i class="generic-icons-nav fa fa-fw fa-sort"></i>{{$orderapps}}</a>
				{{/if}}
			</div>
		</li>
	</ul>
</div>
<div class="collapse d-lg-none" id="navbar-collapse-2">
	<div class="navbar-nav mr-auto">
		{{if $channel_apps.0}}
		{{foreach $channel_apps as $channel_app}}
		{{$channel_app|replace:'dropdown-item':'nav-link'}}
		{{/foreach}}
		<div class="dropdown-header text-white-50 sys-apps-toggle" onclick="openClose('sys-apps-collapsed');">
			{{$sysapps_toggle}}
		</div>
		<div id="sys-apps-collapsed" style="display:none;">
		{{/if}}
		{{if $navbar_apps}}
		{{foreach $navbar_apps as $navbar_app}}
		{{$navbar_app|replace:'dropdown-item':'nav-link'}}
		{{/foreach}}
		<div class="dropdown-divider collapsed-divider"></div>
		{{/if}}

		{{foreach $nav_apps as $nav_app}}
		{{$nav_app|replace:'dropdown-item':'nav-link'}}
		{{/foreach}}
		{{if $channel_apps.0}}
		</div>
		{{/if}}
		{{if $is_owner}}
		<div class="dropdown-divider collapsed-divider"></div>
		<a class="nav-link" href="/apps"><i class="generic-icons-nav fa fa-fw fa-asterisk"></i>{{$manageapps}}</a>
		<a class="nav-link" href="/apps/available"><i class="generic-icons-nav fa fa-fw fa-plus-circle"></i>{{$addapps}}</a>
		<a class="nav-link" href="/apporder"><i class="generic-icons-nav fa fa-fw fa-sort"></i>{{$orderapps}}</a>
		{{/if}}
	</div>
</div>
