<script type="text/javascript">
// This is the init method called when the cronk environment is ready
Cronk.util.initEnvironment("<?php echo $rd->getParameter('parentid'); ?>", function() {
    var CE = this;
    var parentCmp = this.getParent();
    var url = '<?php echo $ro->gen("cronks.eventdb.events.list"); ?>';
    
    var eventStore = new Ext.data.JsonStore({
    	autoLoad: false,
    	autoDestroy: true,
    	baseParams: {
    		offset:0,
    		limit:25
    	},
    	paramNames: {
    		start: 'offset'
    	},
    	url: url,
    	root: 'events',
    	fields: [
            {name: 'event_id'},
            {name: 'event_host'},
            {name: 'event_facility'},
            {name: 'event_priority'},
            {name: 'event_level'},
            {name: 'event_tag'},
            {name: 'event_program'},
            {name: 'event_created'},
            {name: 'event_modified'},
            {name: 'event_message'},
            {name: 'event_ack'},
            {name: 'event_type'}
        ]
    });
    
    var commentStore = new Ext.data.JsonStore({
        autoLoad: false,
        autoDestroy: true,
        baseParams: {
            offset:0,
            limit:25
        },
        paramNames: {
            start: 'offset'
        },
        url: '<?php echo $ro->gen("cronks.eventdb.events.event.comments.list"); ?>',
        root: 'comments',
        fields: [
            {name: 'comment_id'},
            {name: 'comment_author'},
            {name: 'comment_msg'},
            {name: 'comment_type'},
            {name: 'comment_created'}
        ]
    });
    
    var fm = new Cronk.EventDB.FilterManager({url: url, parentCmp: parentCmp});
    fm.addListener('applyFilter', function(filters) {    	
    	var filters_ = {};

    	Ext.each(filters, function(filter, i) {
    		for (var key in filter) {
    			filters_['filter[' + i + '][' + key + ']'] = filter[key];
    		}
    	});
    	
    	eventGrid.fireEvent('statechange');
    	
    	eventStore.baseParams = Ext.apply(filters_, {offset:0, limit:25});
    	
    	eventStore.load();
    });
    
	var checkColumn = function(config){
	    Ext.apply(this, config);
	    if(!this.id){
	        this.id = Ext.id();
	    }
	    this.initialValues = {};
	    this.renderer = this.renderer.createDelegate(this);
	};
	
	checkColumn.prototype = {
	    init: function(grid){
	        this.grid = grid;
	        this.grid.on('render', function(){
	            var view = this.grid.getView();
	            view.mainBody.on('mousedown', this.onMouseDown, this);
	        }, this);
	    },
	
	    onMouseDown: function(e, t){
	        if(Ext.fly(t).hasClass(this.createId())){
	            e.stopEvent();
	            var index = this.grid.getView().findRowIndex(t);
	            var record = this.grid.store.getAt(index);
	            record.set(this.dataIndex, record.data[this.dataIndex] == 0 ? 1 : 0);
	        }
	    },
	
	    renderer: function(v, p, record) {
	    	if (this.initialValues[record.id]) {
	    		if (this.initialValues[record.id] == v) {
	    			this.initialValues[record.id] = null;
	    		    record.reject();
	    		}
                if (this.grid.store.getModifiedRecords().length) {
                    this.grid.commentButton.enable();
                } else {
                    this.grid.commentButton.disable();
                }
	    	} else {
	    		this.initialValues[record.id] = v;
	    	}
	        p.css += ' x-grid3-check-col-td';
	        return String.format('<div class="x-grid3-check-col{0} {1}">&#160;</div>', v != 0 ? '-on' : '', this.createId());
	    },
	
	    createId: function(){
	        return 'x-grid3-cc-' + this.id;
	    }
	};
    
    var ack = new checkColumn({
       header: 'Ack',
       dataIndex: 'event_ack',
       width: 55    
    });

    var _commentGrid = Ext.extend(Ext.grid.GridPanel, {
    	store: commentStore,
        stateId: 'event_db-commentGrid-' + this.id,
        stateful: true,
        colModel: new Ext.grid.ColumnModel({
            defaults: {
                width: 80,
                sortable: true
            },
            columns: [
                {header: _('Type'), dataIndex: 'comment_type'},
                {header: _('Author'), dataIndex: 'comment_author'},
                {header: _('Created'), dataIndex: 'comment_created', width: 150},
                {header: _('Message'), dataIndex: 'comment_msg', width: 200},
            ]           
        }),
        bbar: new Ext.PagingToolbar({
            pageSize: 25,
            store: commentStore,
            displayInfo: true,
            displayMsg: _('Displaying comments {0} - {1} of {2}'),
            emptyMsg: _('No comments to display')
        }),
        frame: true,
        border: false,
        getState: function() {
            var state = {
                height: this.getHeight(),
                width: this.getWidth(),
                storeParams: this.store.baseParams
            };
            this.resumeEvents();
        },
        applyState: function(state) {
            this.setHeight(state.height);
            this.setWidth(state.width);
            this.store.baseParams = state.storeParams;
        }
    });
    
    var commentGrid = new _commentGrid();
    
    var commentForm = (function() {
    	oWin = null;

    	return {
    		show : function() {
		        if(!oWin){
		            oWin = new Ext.Window({
		            	title: _('Add comment'),
		                layout: 'fit',
		                region: 'center',
		                width: 500,
		                height: 320,
		                closeAction: 'hide',
		                plain: false,
		                modal: true,
		                items: new Ext.FormPanel({
		                	labelAlign: 'top',
		                	frame: true,
		                	url: '<?php echo $ro->gen("cronks.eventdb.events.comments.add"); ?>',
		                	items: [{
			                	xtype: 'textfield',
			                	fieldLabel: _('Author'),
			                	readOnly: true,
			                	name: 'author',
			                	allowBlank: false,
			                	width: 460,
                                height: 20,
			                	value: '<?php echo $us->getNsmUser()->user_name; ?>'
			                },{
			                	xtype: 'textarea',
			                	fieldLabel: _('Comment'),
			                	name: 'comment',
			                	width: 460,
			                	height: 130,
			                	allowBlank: true
			                }],
	                        buttons: [{
	                            text: 'Submit',
	                            handler: function() {
	                            	oForm = this.findParentByType(Ext.FormPanel);
	                            	if (oForm.getForm().isValid()) {
		                            	var events = [];
		                            	var params = {};
	                                    Ext.each(eventGrid.getSelectionModel().getSelections(), function(r) {
	                                    	if (r.get('event_ack') != 0) {
	                                            events.push(r.get('event_id'));
	                                    	}
	                                    });                        	
		                            	if (eventStore.getModifiedRecords().length) {
		                            		Ext.each(eventStore.getModifiedRecords(), function(r) {
		                            			var eId = r.get('event_id');
		                            			if (events.indexOf(eId) == -1) {
		                            				events.push(eId);
		                            			}
		                            			params['event_ack[' + eId + ']'] = r.get('event_ack');
		                            		});
		                            	}
	
		                            	oForm.getForm().submit({
		                            		params: Ext.apply({'events[]': events}, params),
		                            		success: function(oForm, action) {
		                            			AppKit.notifyMessage(_('Request successful'), action.result.message);
		                            			// TODO: Reload only if selected.
		                            			eventStore.load();
								if ('event' in commentStore.baseParams) {
			                            			commentStore.load();
								}
		                            		}
		                            	});
		                            	oWin.hide();
	                            	}
	                            }
	                        },{
	                            text: 'Close',
	                            handler: function() {
	                                oWin.hide();
	                            }
	                        }]
		                })
		            });
		            parentCmp.add(Ext.clean(oWin));
		            parentCmp.doLayout();
		        }
		        oWin.show(this);
		    }
    	}
    })();

    var eventTypeColorMap = {
    	syslog: 'style="color:#00ff00"',
    	snmptrap: 'style="color:#66bbff;"',
    	default_: 'style="color:#000000;"'
    };
    
    var eventTypeColorRenderer = function(v, m, r) {
    	m.attr = eventTypeColorMap[v] || eventTypeColorMap.default_;
    	return v;
    };
    
    var eventPriorityColorMap = {
    	alert: 'style="color:#ff0000"',
    	err: 'style="color:#FF8000"',
    	warning: 'style="color:#ffff00"',
    	crit: 'style="color:#FFA0A0"'
    };
    
    var eventPriorityColorRenderer = function(v, m, r) {
        m.attr = eventPriorityColorMap[v] || eventPriorityColorMap.default_;
        return v;
    };
    
    var _eventGrid = Ext.extend(Ext.grid.GridPanel, {
	    constructor: function() {
	        this.addEvents({
	            'statechange': true,
	            'hostFilterChanged': true
	        });
	        
	        Ext.grid.GridPanel.prototype.constructor.call(this);
	    },
        store: eventStore,
        stateId: 'event_db-eventGrid-' + this.id,
        stateful: true,
        stateEvents: ['statechange'],
        tbar: [{
            iconCls: 'icinga-icon-arrow-refresh',
            text: _('Refresh'),
            tooltip: _('Refresh the data in the grid'),
            handler: function(oBtn, e) { eventStore.load(); },
            scope: this
        },{
            iconCls: 'icinga-icon-cog',
            text: _('Settings'),
            menu: {
                items: [{
                    text: _('Auto refresh'),
                    checked: false,

                    checkHandler: function(checkItem, checked) {
                        if (checked == true) {
                            this.trefresh = AppKit.getTr().start({
                                run: function() {
                            	   eventStore.load();
                                },
                                interval: 120000,
                                scope: this
                            });
                        }
                        else {
                            AppKit.getTr().stop(this.trefresh);
                            delete this.trefresh;
                        }   
                    }
                }]
            },
            scope: this
        },'-',{
            text: _('Filter'),
            iconCls: 'icinga-icon-pencil',
            menu: {
                items: [{
                    text: _('Edit'),
                    iconCls: 'icinga-icon-application-form',
                    handler: function() {
                        fm.show();
                    },
                    scope: this
                },{
                    text: _('Remove'),
                    iconCls: 'icinga-icon-cancel',
                    handler: function() {
                    	fm.show(true);
                    	fm.clearFilterFields();
                    	eventStore.baseParams = {offset:0, limit:25};
                    	eventStore.load();
                    },
                    scope: this
                }]
            }
        },'-',{
            text:'Acknoledge',
            tooltip:'Add comment to your acknoledgement',
            iconCls:'icinga-icon-application-form',
            ref: '../commentButton',
            handler: function() { commentForm.show(); },
            disabled: true
        }],
        columns: [ack, {
	        	dataIndex: 'event_id',
	            id: 'event_id',
	            header: 'ID',
	            sortable: true,
	            width: 75
            },{
                dataIndex: 'event_type',
                header: 'Type',
                sortable: true,
                width: 100,
                renderer: eventTypeColorRenderer
            },{
            	dataIndex: 'event_host',
            	header: 'Host',
            	sortable: true,
            	width: 100
            },{
            	dataIndex: 'event_facility',
            	header: 'Facility',
            	sortable: true,
            	width: 100
            },{
            	dataIndex: 'event_priority',
            	header: 'Priority',
            	sortable: true,
            	width: 100,
            	renderer: eventPriorityColorRenderer
            },{
            	dataIndex: 'event_level',
            	header: 'Level',
            	sortable: true,
            	width:
            	100
            },{
            	dataIndex: 'event_tag',
            	header: 'Tag',
            	sortable: true,
            	width:
            	75
            },{
            	dataIndex: 'event_program',
            	header: 'Program',
            	sortable: true,
            	width: 100
            },{
            	dataIndex: 'event_created',
            	header: 'Datetime',
            	sortable: true,
            	width: 100
            },{
            	dataIndex: 'event_message',
            	header: 'Message',
            	sortable: true,
            	width: 100
        }],
        sm: new Ext.grid.RowSelectionModel({
        	listeners: {
        		defaults: {
        			scope: this
        		},
        		rowselect: function(sm, index, r) {
        			sm.grid.commentButton.disable();
        			sm.each(function(record) {
        				if (record.get('event_ack') != 0) {
        					sm.grid.commentButton.enable();
        				}
                        if (sm.grid.store.getModifiedRecords().length) {
                            sm.grid.commentButton.enable();
                        }
        			}, this);
        		}
        	}
        }),
        plugins: ack,
        bbar: new Ext.PagingToolbar({
            pageSize: 25,
            store: eventStore,
            displayInfo: true,
            displayMsg: _('Displaying events {0} - {1} of {2}'),
            emptyMsg: _('No events to display')
        }),
        autoScroll: true,
        listeners: {
        	defaults: {
        		scope: this
        	},
        	rowclick: function(grid, rowIndex, e) {
        		commentStore.baseParams = {event: grid.getStore().getAt(rowIndex).get('event_id'), offset: 0, limit: 25};
        		commentStore.load();
        		commentGrid.findParentByType('panel').show();
        		commentGrid.findParentByType('panel').expand();		
        	},
        	beforerender: function(_this) {
        		_this.fireEvent('hostFilterChanged', _this, true);
        	},
            hostFilterChanged: function(_this, fromrender) {
            	fromrender = fromrender || false;
                if (parentCmp.hostFilter) {
                    var filters = [{
                        'name': 'Host',
                        'type': 'text',
                        'operator': 50,
                        'column': 'event_host',
                        'value': parentCmp.hostFilter,
                        'target': 'event_host'
                    }]
                    fm.addFilterFields(filters);
                    eventGrid.store.baseParams = {
                    	offset:0,
                    	limit:25,
                    	'filter[0][target]': 'event_host',
                    	'filter[0][column]': 'event_host',
                    	'filter[0][operator]': 50,
                    	'filter[0][value]': parentCmp.hostFilter
                    };
                    
                    if (!fromrender) {
	                    
	                    eventGrid.store.load();
	                    
                    }
                    
                    _this.fireEvent('statechanged');
                }
            }
        },
        border: false,
        getState: function() {
        	var state = {
        		height: this.getHeight(),
        		width: this.getWidth(),
        		storeParams: this.store.baseParams,
        		filters: fm.getFilters()
        	};
        	return state;
        },
        applyState: function(state) {
        	this.setHeight(state.height);
        	this.setWidth(state.width);
        	this.store.baseParams = state.storeParams;
        	fm.addFilterFields(state.filters);
        }
    });
    
    eventGrid = new _eventGrid();
    
    var _IcingaEventDBCronk = Ext.extend(Ext.Container, {
	    constructor: function(config) {
	        Ext.Container.prototype.constructor.call(this, config);
	    },
	    
	    applyHostFilter: function() {
	    	eventGrid.fireEvent('hostFilterChanged', eventGrid);
	    }
    });
    
    var IcingaEventDBCronk = new _IcingaEventDBCronk({
    	layout: 'border',
        width: parentCmp.getInnerWidth()*0.98,
        items: [{
            region:'center',
            xtype:'panel',
            layout:'fit',
            border:false,
            items: eventGrid
        },{
            xtype: 'panel',
            region: 'east',
            width: 520,
            collapsible: true,
            split: true,
            collapsed: true,
            hidden: true,
            layout: 'fit',
            border: false,
            items: commentGrid
        }]
    });
    
    CE.add(IcingaEventDBCronk);
    CE.doLayout();

    eventStore.load();
});
</script>
