<div class="wall-item-like-buttons" id="wall-item-like-buttons-{{$id}}">
	<i class="fa fa-thumbs-o-up item-tool btn btn-outline-secondary" title="{{$likethis}}" onclick="dolike({{$id}},'Like'); return false"></i>
	<i class="fa fa-thumbs-o-down item-tool btn btn-outline-secondary" title="{{$nolike}}" onclick="dolike({{$id}},'Dislike'); return false"></i>
<div id="like-rotator-{{$id}}" class="like-rotator"></div>
</div>
