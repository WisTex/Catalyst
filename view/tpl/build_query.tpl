<script> 

	var bParam_cmd = "{{$baseurl}}/update/{{$pgtype}}";


	var bParam_uid = {{$uid}};
	// lists can be either type string (virtual lists) or integer (normal accesslists)
	var bParam_gid = "{{$gid}}";
	var bParam_cid = {{$cid}};
	var bParam_cmin = {{$cmin}};
	var bParam_cmax = {{$cmax}};
	var bParam_star = {{$star}};
	var bParam_liked = {{$liked}};
	var bParam_conv = {{$conv}};
	var bParam_spam = {{$spam}};
	var bParam_new = {{$nouveau}};
	var bParam_page = {{$page}};
	var bParam_wall = {{$wall}};
	var bParam_draft = {{$draft}};
	var bParam_list = {{$list}};
	var bParam_fh = {{$fh}};
	var bParam_dm = {{$dm}};
	var bParam_static = {{$static}};

	var bParam_search = "{{$search}}";
	var bParam_xchan = "{{$xchan}}";
	var bParam_order = "{{$order}}";
	var bParam_file = "{{$file}}";
	var bParam_cats = "{{$cats}}";
	var bParam_tags = "{{$tags}}";
	var bParam_dend = "{{$dend}}";
	var bParam_dbegin = "{{$dbegin}}";
	var bParam_mid = "{{$mid}}";
	var bParam_verb = "{{$verb}}";
	var bParam_net = "{{$net}}";
	var bParam_pf = "{{$pf}}";

	function buildCmd() {
		var udargs = ((page_load) ? "/load" : "");
		var bCmd = bParam_cmd + udargs + "?f=" ;
		if(bParam_uid) bCmd = bCmd + "&p=" + bParam_uid;
		if(bParam_cmin != (-1)) bCmd = bCmd + "&cmin=" + bParam_cmin;
		if(bParam_cmax != (-1)) bCmd = bCmd + "&cmax=" + bParam_cmax;
		if(bParam_gid != 0) { bCmd = bCmd + "&gid=" + bParam_gid; } else
		if(bParam_cid != 0) { bCmd = bCmd + "&cid=" + bParam_cid; }
		if(bParam_static != 0) { bCmd = bCmd + "&static=" + bParam_static; }
		if(bParam_star != 0) bCmd = bCmd + "&star=" + bParam_star;
		if(bParam_liked != 0) bCmd = bCmd + "&liked=" + bParam_liked;
		if(bParam_conv!= 0) bCmd = bCmd + "&conv=" + bParam_conv;
		if(bParam_spam != 0) bCmd = bCmd + "&spam=" + bParam_spam;
		if(bParam_new != 0) bCmd = bCmd + "&new=" + bParam_new;
		if(bParam_wall != 0) bCmd = bCmd + "&wall=" + bParam_wall;
		if(bParam_draft != 0) bCmd = bCmd + "&draft=" + bParam_draft;
		if(bParam_list != 0) bCmd = bCmd + "&list=" + bParam_list;
		if(bParam_fh != 0) bCmd = bCmd + "&fh=" + bParam_fh;
		if(bParam_dm != 0) bCmd = bCmd + "&dm=" + bParam_dm;
		if(bParam_search != "") bCmd = bCmd + "&search=" + bParam_search;
		if(bParam_xchan != "") bCmd = bCmd + "&xchan=" + bParam_xchan;
		if(bParam_order != "") bCmd = bCmd + "&order=" + bParam_order;
		if(bParam_file != "") bCmd = bCmd + "&file=" + bParam_file;
		if(bParam_cats != "") bCmd = bCmd + "&cat=" + bParam_cats;
		if(bParam_tags != "") bCmd = bCmd + "&tag=" + bParam_tags;
		if(bParam_dend != "") bCmd = bCmd + "&dend=" + bParam_dend;
		if(bParam_dbegin != "") bCmd = bCmd + "&dbegin=" + bParam_dbegin;
		if(bParam_mid != "") bCmd = bCmd + "&mid=" + bParam_mid;
		if(bParam_verb != "") bCmd = bCmd + "&verb=" + bParam_verb;
		if(bParam_net != "") bCmd = bCmd + "&net=" + bParam_net;
		if(bParam_page != 1) bCmd = bCmd + "&page=" + bParam_page;
		if(bParam_pf != 0) bCmd = bCmd + "&pf=" + bParam_pf;
		return(bCmd);
	}

</script>

