{{if $item.comment_firstcollapsed}}
<div class="hide-comments-outer fakelink" onclick="showHideComments({{$item.id}});">
	<span id="hide-comments-{{$item.id}}" class="hide-comments">{{$item.hide_text}}</span>&nbsp;<span id="hide-comments-total-{{$item.id}}" class="hide-comments-total">{{$item.num_comments}}</span>
</div>
<div id="collapsed-comments-{{$item.id}}" class="collapsed-comments" style="display: none;">
{{/if}}
	<div id="thread-wrapper-{{$item.id}}" class="thread-wrapper{{if $item.toplevel}} {{$item.toplevel}} generic-content-wrapper h-entry {{else}} u-comment h-cite {{/if}} item_{{$item.submid}}">
		<a name="item_{{$item.id}}" ></a>
		<div class="wall-item-outside-wrapper{{if $item.is_comment}} comment{{/if}}{{if $item.previewing}} preview{{/if}}" id="wall-item-outside-wrapper-{{$item.id}}" >
			<div class="clearfix wall-item-content-wrapper{{if $item.is_comment}} comment{{/if}}" id="wall-item-content-wrapper-{{$item.id}}">
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
					{{if $item.title_tosource}}{{if $item.plink}}<a href="{{$item.plink.href}}" title="{{$item.title}} ({{$item.plink.title}})" rel="nofollow noopener">{{/if}}{{/if}}{{$item.title}}{{if $item.title_tosource}}{{if $item.plink}}</a>{{/if}}{{/if}}
				</div>
				{{if ! $item.is_new}}
				<hr class="m-0">
				{{/if}}
				{{/if}}
				<div class="p-2 clearfix wall-item-head{{if $item.is_new && !$item.title && !$item.event && !$item.is_comment}} wall-item-head-new rounded-top{{/if}}">
					<div class="wall-item-info" id="wall-item-info-{{$item.id}}" >
						<div class="wall-item-photo-wrapper{{if $item.owner_url}} wwfrom{{/if}} h-card p-author" id="wall-item-photo-wrapper-{{$item.id}}">
							<img src="{{$item.thumb}}" class="fakelink wall-item-photo{{$item.sparkle}} u-photo p-name" id="wall-item-photo-{{$item.id}}" alt="{{$item.name}}" data-bs-toggle="dropdown" /></a>
							{{if $item.thread_author_menu}}
							<i class="fa fa-caret-down wall-item-photo-caret cursor-pointer" data-bs-toggle="dropdown"></i>
							<div class="dropdown-menu">
								<img src="{{$item.large_avatar}}" style="width: 200px; height: 200px;" id="wall-item-popup-photo-{{$item.id}}" alt="{{$item.name}}" />
								<div style="margin-top: 20px;">
								<hr>
								{{foreach $item.thread_author_menu as $mitem}}
								<a class="dropdown-item" {{if $mitem.href}}href="{{$mitem.href}}"{{/if}} {{if $mitem.action}}onclick="{{$mitem.action}}"{{/if}} {{if $mitem.title}}title="{{$mitem.title}}"{{/if}} >{{$mitem.title}}</a>
								{{/foreach}}
								</div>
							</div>
							{{/if}}
						</div>
					</div>
					{{if $item.lock}}
					<div class="wall-item-lock dropdown">
						<i class="fa fa-lock lockview" data-bs-toggle="dropdown" title="{{$item.lock}}" onclick="lockview('item',{{$item.id}});" ></i>&nbsp;
						<div id="panel-{{$item.id}}" class="dropdown-menu"></div>
					</div>
					{{/if}}
					<div class="wall-item-author">
						<a href="{{$item.profile_url}}" title="{{$item.linktitle}}" class="wall-item-name-link u-url"><span class="wall-item-name{{$item.sparkle}}" id="wall-item-name-{{$item.id}}" >{{$item.name}}</span></a>{{if $item.owner_url}}&nbsp;{{$item.via}}&nbsp;<a href="{{$item.owner_url}}" title="{{$item.olinktitle}}" class="wall-item-name-link"><span class="wall-item-name{{$item.osparkle}}" id="wall-item-ownername-{{$item.id}}">{{$item.owner_name}}</span></a>{{/if}}
					</div>
					<div class="wall-item-ago"  id="wall-item-ago-{{$item.id}}">
						{{if $item.location}}<span class="wall-item-location p-location" id="wall-item-location-{{$item.id}}">{{$item.location}},&nbsp;</span>{{/if}}<span class="autotime" title="{{$item.isotime}}"><time class="dt-published" datetime="{{$item.isotime}}">{{$item.localtime}}</time>{{if $item.editedtime}}&nbsp;{{$item.editedtime}}{{/if}}{{if $item.expiretime}}&nbsp;{{$item.expiretime}}{{/if}}</span>{{if $item.editedtime}}&nbsp;<i class="fa fa-pencil"></i>{{/if}}&nbsp;{{if $item.app}}<span class="item.app">{{$item.str_app}}</span>{{/if}}
					</div>
				</div>

				{{if $item.body}}
				<div class="p-2 wall-item-content clearfix" id="wall-item-content-{{$item.id}}">
					<div class="wall-item-body e-content" id="wall-item-body-{{$item.id}}" >
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
					<div class="float-end wall-item-tools-right">
						{{if $item.toplevel && $item.emojis && $item.reactions}}
						<div class="btn-group">
							<button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" id="wall-item-react-{{$item.id}}">
								<i class="fa fa-smile-o"></i>
							</button>
							<div class="dropdown-menu dropdown-menu-right">
							{{foreach $item.reactions as $react}}
								<a class="dropdown-item clearfix" href="#" onclick="jotReact({{$item.id}},'{{$react}}'); return false;"><img class="menu-img-2" src="/images/emoji/{{$react}}.png" alt="{{$react}}" /></a>
							{{/foreach}}
							</div>
						</div>
						{{/if}}
						<div class="btn-group">
							{{if $item.like}}
							<button type="button" title="{{if $item.my_responses.like}}{{$item.like.1}}{{else}}{{$item.like.0}}{{/if}}" class="btn btn-outline-secondary btn-sm" onclick="dolike({{$item.id}},{{if $item.my_responses.like}} 'Undo/' + {{/if}} 'Like' ); return false;">
								<i class="fa fa-thumbs-o-up{{if $item.my_responses.like}} ivoted{{/if}}" ></i>
							</button>
							{{/if}}
							{{if $item.dislike}}
							<button type="button" title="{{if $item.my_responses.dislike}}{{$item.dislike.1}}{{else}}{{$item.dislike.0}}{{/if}}" class="btn btn-outline-secondary btn-sm" onclick="dolike({{$item.id}},{{if $item.my_responses.dislike}} 'Undo/' + {{/if}} 'Dislike'); return false;">
								<i class="fa fa-thumbs-o-down{{if $item.my_responses.dislike}} ivoted{{/if}}" ></i>
							</button>
							{{/if}}
							{{if $item.isevent}}
							<div class="btn-group">
								<button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" id="wall-item-attend-menu-{{$item.id}}" title="{{$item.attend_title}}">
									<i class="fa fa-calendar-check-o"></i>
								</button>
								<div class="dropdown-menu">
									<a class="dropdown-item" href="#" title="{{if $item.my_responses.attend}}{{$item.undo_attend}}{{else}}{{$item.attend.0}}{{/if}}" onclick="itemAddToCal({{$item.id}}); dolike({{$item.id}},{{if $item.my_responses.attend}} 'Undo/' + {{/if}} 'Accept'); return false;">
										<i class="item-act-list fa fa-check{{if $item.my_responses.attend}} ivoted{{/if}}" ></i> {{$item.attend.0}}
									</a>
									<a class="dropdown-item" href="#" title="{{if $item.my_responses.attendno}}{{$item.undo_attend}}{{else}}{{$item.attend.1}}{{/if}}" onclick="itemAddToCal({{$item.id}}), dolike({{$item.id}},{{if $item.my_responses.attendno}} 'Undo/' + {{/if}} 'Reject'); return false;">
										<i class="item-act-list fa fa-times{{if $item.my_responses.attendno}} ivoted{{/if}}" ></i> {{$item.attend.1}}
									</a>
									<a class="dropdown-item" href="#" title="{{if $item.my_responses.attendmaybe}}{{$item.undo_attend}}{{else}}{{$item.attend.2}}{{/if}}" onclick="itemAddToCal({{$item.id}}); dolike({{$item.id}},{{if $item.my_responses.attendmaybe}} 'Undo/' {{/if}} 'TentativeAccept'); return false;">
										<i class="item-act-list fa fa-question{{if $item.my_responses.attendmaybe}} ivoted{{/if}}" ></i> {{$item.attend.2}}
									</a>
								</div>
							</div>
							{{/if}}
							<div class="btn-group">
								<button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" id="wall-item-menu-{{$item.id}}">
									<i class="fa fa-cog"></i>
								</button>
								<div class="dropdown-menu dropdown-menu-right" role="menu" aria-labelledby="wall-item-menu-{{$item.id}}">
									{{if $item.embed}}
									<a class="dropdown-item" href="#" onclick="jotEmbed({{$item.id}},{{$item.item_type}}); return false"><i class="generic-icons-nav fa fa-fw fa-share" title="{{$item.embed}}"></i>{{$item.embed}}</a>
									{{/if}}
									{{if $item.plink}}
									<a class="dropdown-item" href="{{$item.plink.href}}" title="{{$item.plink.title}}" class="u-url"><i class="generic-icons-nav fa fa-fw fa-external-link"></i>{{$item.plink.title}}</a>
									{{/if}}
									{{if $item.edpost}}
									<a class="dropdown-item" href="{{$item.edpost.0}}" title="{{$item.edpost.1}}"><i class="generic-icons-nav fa fa-fw fa-pencil"></i>{{$item.edpost.1}}</a>
									{{/if}}
									{{if $item.tagger}}
									<a class="dropdown-item" href="#"  onclick="itemTag({{$item.id}}); return false;"><i id="tagger-{{$item.id}}" class="generic-icons-nav fa fa-fw fa-tag" title="{{$item.tagger.tagit}}"></i>{{$item.tagger.tagit}}</a>
									{{/if}}
									{{if $item.filer}}
									<a class="dropdown-item" href="#" onclick="itemFiler({{$item.id}}); return false;"><i id="filer-{{$item.id}}" class="generic-icons-nav fa fa-fw fa-folder-open" title="{{$item.filer}}"></i>{{$item.filer}}</a>
									{{/if}}
									{{if $item.bookmark}}
									<a class="dropdown-item" href="#" onclick="itemBookmark({{$item.id}}); return false;"><i id="bookmarker-{{$item.id}}" class="generic-icons-nav fa fa-fw fa-bookmark" title="{{$item.bookmark}}"></i>{{$item.bookmark}}</a>
									{{/if}}
									{{if $item.addtocal}}
									<a class="dropdown-item" href="#" onclick="itemAddToCal({{$item.id}}); return false;"><i id="addtocal-{{$item.id}}" class="generic-icons-nav fa fa-fw fa-calendar" title="{{$item.addtocal}}"></i>{{$item.addtocal}}</a>
									{{/if}}
									{{if $item.star}}
									<a class="dropdown-item" href="#" onclick="dostar({{$item.id}}); return false;"><i id="starred-{{$item.id}}" class="generic-icons-nav fa fa-fw{{if $item.star.isstarred}} starred fa-star{{else}} unstarred fa-star-o{{/if}}" title="{{$item.star.toggle}}"></i>{{$item.star.toggle}}</a>
									{{/if}}
									{{if $item.thread_action_menu}}
									{{foreach $item.thread_action_menu as $mitem}}
									<a class="dropdown-item" {{if $mitem.href}}href="{{$mitem.href}}"{{/if}} {{if $mitem.action}}onclick="{{$mitem.action}}"{{/if}} {{if $mitem.title}}title="{{$mitem.title}}"{{/if}} ><i class="generic-icons-nav fa fa-fw fa-{{$mitem.icon}}"></i>{{$mitem.title}}</a>
									{{/foreach}}
									{{/if}}
									{{if $item.drop.dropping}}
									<a class="dropdown-item" href="#" onclick="dropItem('item/drop/{{$item.id}}', '#thread-wrapper-{{$item.id}}'); return false;" title="{{$item.drop.delete}}" ><i class="generic-icons-nav fa fa-fw fa-trash-o"></i>{{$item.drop.delete}}</a>
									{{/if}}
									<div class="dropdown-divider"></div>
									{{if $item.edpost && $item.dreport}}
									<a class="dropdown-item" href="dreport/?mid={{$item.mid}}">{{$item.dreport}}</a>
									{{/if}}
								</div>
							</div>
						</div>
					</div>
					<div id="like-rotator-{{$item.id}}" class="like-rotator"></div>
					<div class="wall-item-tools-left btn-group"  id="wall-item-tools-left-{{$item.id}}">
						{{if $item.star && $item.star.isstarred}}
						<div class="btn-group" id="star-button-{{$item.id}}">
							<button type="button" class="btn btn-outline-secondary btn-sm wall-item-like" onclick="dostar({{$item.id}});"><i class="fa fa-star"></i></button>
						</div>
						{{/if}}
						{{if $item.attachments}}
						<div class="btn-group">
							<button type="button" class="btn btn-outline-secondary btn-sm wall-item-like dropdown-toggle" data-bs-toggle="dropdown" id="attachment-menu-{{$item.id}}"><i class="fa fa-paperclip"></i></button>
							<ul class="dropdown-menu" role="menu" aria-labelledby="attachment-menu-{{$item.id}}">{{$item.attachments}}</ul>
						</div>
						{{/if}}
						<div class="wall-item-list-comments btn-group">
							<a class="btn btn-outline-secondary btn-sm" href="{{$item.viewthread}}">
								{{$item.comment_count_txt}}{{if $item.unseen_comments}}<span class="unseen-wall-indicator-{{$item.id}}">, {{$item.list_unseen_txt}}</span>{{/if}}
							</a>
						</div>
						{{if $item.unseen_comments}}
						<div class="unseen-wall-indicator-{{$item.id}} btn-group">
							<button class="btn btn-outline-secondary btn-sm" title="{{$item.markseen}}" onclick="markItemRead({{$item.id}}); return false;">
								<i class="fa fa-check-square-o"></i>
							</button>
						</div>
						{{/if}}
						{{if $item.responses }}
						{{foreach $item.responses as $verb=>$response}}
						{{if $response.count}}
						<div class="btn-group">
							<button type="button" class="btn btn-outline-secondary btn-sm wall-item-like dropdown-toggle"{{if $response.modal}} data-bs-toggle="modal" data-bs-target="#{{$verb}}Modal-{{$item.id}}"{{else}} data-bs-toggle="dropdown"{{/if}} id="wall-item-{{$verb}}-{{$item.id}}">{{$response.button}}</button>
							{{if $response.modal}}
							<div class="modal" id="{{$verb}}Modal-{{$item.id}}">
								<div class="modal-dialog">
									<div class="modal-content">
										<div class="modal-header">
											<h4 class="modal-title">{{$response.button}}</h4>
											<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
										</div>
										<div class="modal-body response-list">
											<ul class="nav nav-pills flex-column">{{foreach $response.list as $liker}}<li class="nav-item">{{$liker}}</li>{{/foreach}}</ul>
										</div>
										<div class="modal-footer clear">
											<button type="button" class="btn btn-outline-secondary" data-dismiss="modal">{{$item.modal_dismiss}}</button>
										</div>
									</div><!-- /.modal-content -->
								</div><!-- /.modal-dialog -->
							</div><!-- /.modal -->
							{{else}}
							<div class="dropdown-menu">{{foreach $response.list as $liker}}{{$liker}}{{/foreach}}</div>
							{{/if}}
						</div>
						{{/if}}
						{{/foreach}}
						{{/if}}
					</div>
				</div>
			</div>
		</div>
	</div>
{{if $item.comment_lastcollapsed}}
</div>
{{/if}}
