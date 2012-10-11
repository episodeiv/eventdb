Ext.ns('Cronk.EventDB')
Ext.ns("Cronk.EventDB.FilterManagerViews");

/**
 * Filter manager, handles filter window and filter definitions.
 *
 * The filter interface is outsourced to @seeMixins.FilterManagerInterfaceMixin.
 *
 */
Cronk.EventDB.FilterManager = Ext.extend(Ext.util.Observable, {
    constructor: function(cfg) {
        Ext.apply(this,cfg);
        var mixin = new Cronk.EventDB.Mixins.FilterManagerInterfaceMixin();
        for(m in mixin) { // add mixin
            this[m] = mixin[m];
        }
        Ext.util.Observable.prototype.constructor.call(this,cfg);
    },
    
    oWin: null,
    config: {},
	

    clearFilterFields: function() {
        if(!this.oWin)
            return false;
        this.oWin.cascade(function (elem) {
            if(elem.reset)
                elem.reset();
				
        });
        var btns = this.oWin.findByType('button');
        //btns = btns.concat(this.oWin.findByType('cycle'));
        for(var i=0;i<btns.length;i++) {
            if(btns[i].initialConfig.reset)
                btns[i].initialConfig.reset.call(btns[i]);	
        }
        // reset filter
        this.resetFilterObject();
    },

	
    updateFilterDescriptor: function(blank) {
        // window doesn't exist yet, don't update
        if(!this.oWin) {
            return;
        }
        this.resetFilterObject();
        this.oWin.cascade(function(elem) {
            if(elem.getValue)		
                var val = elem.getValue();
            if(!val)
                return true;
				
            switch(elem.name) {
                case 'misc':
                    for(var i=0;i<val.length;i++) {
                        if(val[i].name.split('_')[1] == 'noAck') {
                            this.toggleAcknowledged(false);
                        }
                    }
                    break;
                case 'priorities':
                    for(var i=0;i<val.length;i++) {
                        this.togglePriority(val[i].name.split('_')[1],false);
                    }
                    break;
                case 'facilities':
                    for(var i=0;i<val.length;i++) {
                        this.toggleFacility(val[i].name.split('_')[1]);
                    }
                    break;
                case 'timespan_from':
                    this.setFromTime(val);
                    break;
                case 'timespan_to':
                    this.setToTime(val);
                    break;
                case 'sources':
                    for(var i=0;i<val.length;i++) {
                        this.toggleEventSource(val[i].name.split('_')[1],false);
                    }
                    break
                case 'Host_Include_set':
                    this.setHostIncludes(val);
                    break;
                case 'Host_Exclude_set':
                    this.setHostExcludes(val);
                    break;
                case 'Host_Include_pattern':	
                    this.setHostIncludePattern(val,elem.ownerCt.matchType);
                    break;
                case 'Host_Exclude_pattern':
                    this.setHostExcludePattern(val,elem.ownerCt.matchType);
                    break;
                case 'Program_Include_set':
                    this.setProgramIncludes(val)
                    break;
                case 'Program_Exclude_set':
                    this.setProgramExcludes(val)
                    break;
                case 'Program_Include_pattern':
                    this.setProgramIncludePattern(val,elem.ownerCt.matchType);
                    break;
                case 'Program_Exclude_pattern':
                    this.setProgramExcludePattern(val,elem.ownerCt.matchType);
                    break;
                case 'Message_filter':
                    this.setMessageFilter(val);
                    break;
            }
            return true;
        },this);
        // Cycle buttons need special care
        var cycleBtns = this.oWin.findByType('button'); 
        for(var i=0;i<cycleBtns.length;i++) {
            var val = cycleBtns[i].getActiveItem().value;
            switch(cycleBtns[i].name) {
                case 'rpp':
                    this.setDisplayLimit(val);
                    break;
                case 'order_by':
                    this.setDisplayOrderColumn(val);
                    break;
                case 'order_dir':
                    this.setDisplayDir(val);
                    break;
                case 'group_by':
                    this.setGroupBy(val);
                    break;
            }	
        }
    },
    generalView: null,
    advancedView: null,

    show: function(renderOnly) {
        if(!this.generalView)
            this.generalView =Cronk.EventDB.FilterManagerViews.General();
        if(!this.advancedView)	
            this.advancedView = Cronk.EventDB.FilterManagerViews.Advanced(this.url);
		
        var ev_uid = Ext.id('eventdb_filterwin');
        renderOnly = renderOnly || false;
		
        this.generalView.updateFields(this.getFilterObject());
        this.advancedView.updateFields(this.getFilterObject());
		
        if (!this.oWin) {
            this.oWin = new Ext.Window({
                layout: 'fit',
                closeAction: 'hide',
                region: 'center',
                width: 820,
                height: 550,
                title: _('Filter'),
                items: //new Ext.form.FormPanel({ 
                //	items: 
                new Ext.TabPanel({
                    activeTab: 0,
                    items: [	
                    this.generalView,
                    this.advancedView
                    ]
                }),
                //	}),
                bbar: {
                    defaults: {
                        scope: this
                    },
                    items: [{
                        text: _('Apply'),
                        iconCls: 'icinga-icon-accept',
                        handler: function(oButton, e) {
                            this.updateFilterDescriptor();
                            var desc = this.getFilterObject();
                            this.fireEvent("applyFilter",desc);
                            this.oWin.hide();
                        }
                    },{
                        text: _('Cancel'),
                        iconCls: 'icinga-icon-cross',
                        handler: function() {
                            this.oWin.hide();
                        }
                    },'-',{
                        text: _('Reset'),
                        iconCls: 'icinga-icon-delete',
                        handler: function() {
                            this.clearFilterFields();
                        }
                    }]
                },
                modal: true,
                renderTo: Ext.getBody()
            });
        }
        if (!renderOnly) {
            this.oWin.show();
        }
    }
});
