<div class="generic-content-wrapper">
<div class="section-title-wrapper"><h3>{{$banner}}</h3></div>
<div class="section-content-wrapper">
{{if $hasentries}}

<table id="admin-queue-table">
    <tr>
        <td>{{$numentries}}&nbsp;&nbsp;</td>
        <td>{{$desturl}}</td>
        <td>{{$priority}}</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
    </tr>

{{foreach $entries as $e}}
    <tr>
        <td>{{$e.total}}</td>
        <td>{{$e.outq_posturl}}</td>
        <td>{{$e.priority}}</td>
        <td></td>
        {{if $expert}}
            <td><a href="admin/queue?f=&details={{$e.eurl}}" title="{{$examine}}" class="btn btn-outline-secondary">
                    <i class="fa fa-eye"></i>
                </a>
            </td>
            <td><a href="admin/queue?f=&dropsite={{$e.eurl}}" title="{{$nukesite}}" class="btn btn-outline-secondary">
                    <i class="fa fa-crosshairs"></i>
                </a>
            </td>
            <td><a href="admin/queue?f=&emptysite={{$e.eurl}}" title="{{$empty}}" class="btn btn-outline-secondary">
                    <i class="fa fa-trash-o"></i>
                </a>
            </td>
        {{/if}}
    </tr>
{{/foreach}}
</table>

{{/if}}
</div>
</div>
