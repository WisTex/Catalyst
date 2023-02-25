<div class="modal" id="generic-modal-{{$id}}" tabindex="-1" role="dialog" aria-labelledby="generic-modal-{{$id}}" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="generic-modal-title-{{$id}}">{{$title}}</h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="generic-modal-body-{{$id}}"></div>
      <div class="modal-footer">
        <button id="generic-modal-cancel-{{$id}}" type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{$cancel}}</button>
	{{if $ok}}
        <button id="generic-modal-ok-{{$id}}" type="button" class="btn btn-primary">{{$ok}}</button>
	{{/if}}
      </div>
    </div><!-- /.modal-content -->
  </div><!-- /.modal-dialog -->
</div><!-- /.modal -->
