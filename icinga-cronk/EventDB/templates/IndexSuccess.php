<script type="text/javascript">
Cronk.util.initEnvironment("<?php echo $rd->getParameter('parentid'); ?>", function() {
    var cfg = {
        CE: this,
        parentCmp: this.getParent(),
        eventUrl: '<?php echo $ro->gen("modules.eventdb.events.list"); ?>',
        commentUrl: '<?php echo $ro->gen("modules.eventdb.events.event.comments.list"); ?>',
        commentAddUrl: '<?php echo $ro->gen("modules.eventdb.events.comments.add"); ?>',
        userName: '<?php echo $us->getNsmUser()->user_name; ?>',
        showCopyPaste: '<?php echo AgaviConfig::get("modules.eventdb.showCopyPaste",false); ?>',
        additionalFields:  <?php echo json_encode($rd->getParameter('additionalFields', array())); ?>,
        mailMeUrl: '<?php echo $ro->gen('modules.eventdb.events.event.mailme'); ?>'
    };
    Cronk.EventDB.MainView(cfg);
});
</script>
