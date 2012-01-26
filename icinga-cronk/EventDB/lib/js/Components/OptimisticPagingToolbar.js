Ext.ns("Cronk.EventDB.Components").OptimisticPagingToolbar = (Ext.extend(Ext.PagingToolbar,{
    onLoad : function(store,result,options) {
        Ext.PagingToolbar.prototype.onLoad.apply(this,arguments);
        if(!this.rendered){
            return;
        }
        this.first.setDisabled(false);
        this.prev.setDisabled(false);

        this.next.setDisabled(!Ext.isArray(result) || result.length < (options.limit || 25));

        this.last.setDisabled(true);
        this.last.hide();
    },

    initComponent : function() {
        Ext.PagingToolbar.prototype.initComponent.apply(this,arguments);

        this.first.setDisabled(false);
        this.prev.setDisabled(false);
        this.next.setDisabled(false);
        this.last.setDisabled(true);
        this.last.hide();
    }
}));