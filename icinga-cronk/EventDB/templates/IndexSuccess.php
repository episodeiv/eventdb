<script type="text/javascript">
    Cronk.util.initEnvironment(<?php CronksRequestUtil::echoJsonString($rd); ?>, function() {
    var cfg = {
        parentCmp: this.getParent(),
        eventUrl: '<?php echo $ro->gen("modules.eventdb.events.list"); ?>',
        commentUrl: '<?php echo $ro->gen("modules.eventdb.events.event.comments.list"); ?>',
        commentAddUrl: '<?php echo $ro->gen("modules.eventdb.events.comments.add"); ?>',
        userName: '<?php echo $us->getNsmUser()->user_name; ?>',
        showCopyPaste: '<?php echo AgaviConfig::get("modules.eventdb.showCopyPaste",false); ?>',
        additionalFields:  <?php echo json_encode($rd->getParameter('additionalFields', array())); ?>,
        mailMeUrl: '<?php echo $ro->gen('modules.eventdb.events.event.mailme'); ?>',
        stateful: true,
        stateId: this.stateuid
    };
    var eventdb = Cronk.EventDB.MainView(cfg);
    this.setStatefulObject(eventdb.eventGrid);
    if (Ext.isObject(this.params) && this.params.FilterJSON) {
        if (! Ext.isObject(this.state)) {
            this.state = {};
        }
        this.state.filters = Ext.decode(this.params.FilterJSON);
        delete this.params.FilterJSON;
    }
    if (this.state) {
        eventdb.eventGrid.on({
            beforerender: function(self) {
                self.applyState(this.state);
            },
            scope: this
        });
    }
    this.getParent().on({
        activate: function () {
            if (this.autorefreshEnabled) {
                this.enableAutorefresh();
            } else {
                this.refresh();
            }
        },
        deactivate: function () {
            if (this.autorefreshEnabled) {
                this.disableAutorefresh();
            }
        },
        scope: eventdb.eventGrid
    });
    this.add(eventdb);
    this.doLayout();
});
</script>
