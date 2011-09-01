<script type="text/javascript">
// This is the init method called when the cronk environment is ready
Cronk.util.initEnvironment("<?php echo $rd->getParameter('parentid'); ?>", function() {
    var cfg = {
		CE : this,
    	parentCmp : this.getParent(),
    	eventUrl : '<?php echo $ro->gen("modules.cronks.eventdb.events.list"); ?>',
   		commentUrl : '<?php echo $ro->gen("modules.cronks.eventdb.events.event.comments.list"); ?>',
		commentAddUrl : '<?php echo $ro->gen("modules.cronks.eventdb.events.comments.add"); ?>',
		userName : '<?php echo $us->getNsmUser()->user_name; ?>'
	}
	//hand params over to js
	Cronk.EventDB.MainView(cfg);
});
</script>
