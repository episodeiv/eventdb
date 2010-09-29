Ext.ns('Cronk.EventDB')

Cronk.EventDB.FilterManager = Ext.extend(Ext.util.Observable, {
	
	oWin: null,
	
	config: {},
	
	activeFilters: {},
	
    operators :  {
        text: [
            [60, 'contain'],
            [61, 'does not contain'],
            [50, 'is'],
            [51, 'is not']
        ],

        number: [
            [50, 'is'],
            [51, 'is not'],
            [70, 'less than'],
            [71, 'greater than']
        ],

        ack: [
            [50, 'is'],
            [51, 'is not']
        ]
    },
	
    filters : new Ext.data.ArrayStore({
        autoDestroy: false,
        idIndex: 0,
        fields:['filter_name', 'filter_type', 'filter_target', 'filter_column'],
        data: [
            ['ID', 'number', 'EventDbEvent', 'event_id'],
            ['Type', 'text', 'EventDbEvent', 'event_type'],
            ['Host', 'text', 'EventDbEvent', 'event_host'],
            ['Ack', 'ack', 'EventDbEvent', 'event_ack'],
            ['Facility', 'text', 'EventDbEvent', 'event_facility'],
            ['Priority', 'text', 'EventDbEvent', 'event_priority'],
            ['Level', 'text', 'EventDbEvent', 'event_level'],
            ['Tag', 'text', 'EventDbEvent', 'event_tag'],
            ['Program', 'text', 'EventDbEvent', 'event_program'],
            ['Message', 'text', 'EventDbEvent', 'event_message']
        ]
    }),
	
    constructor: function(config) {
    	Ext.apply(this.config, config);
    	
    	this.addEvents({
    		'applyFilter': true
    	})
    	
    	Ext.util.Observable.prototype.constructor.call(this, config);
    },
    
    addActiveFilter: function(filter) {
    	var id = this.activeFilters.length;
    	this.activeFilters[id] = filter;
    },
    
    getFilters: function() {
    	return this.activeFilters;
    },
    
    clearFilters: function() {
    	this.activeFilters = [];
    },
    
    clearFilterFields: function() {
    	this.oWin.removeAll(true);
    	this.oWin.add(new Ext.FormPanel({
    		layout: 'fit',
    		items: this.addFilterTargetSelector()
    	}));
    	this.oWin.doLayout();
    	this.clearFilters();
    },
    
    applyFilter: function(values) {
    	this.clearFilters();
    	
        if (values.filter_target) {
	        if(!Ext.isArray(values.filter_target)) {
	            values.filter_target = [values.filter_target];
	            values.filter_operator = [values.filter_operator];
	            values.filter_value = [values.filter_value];
	        }
	        
	        for(var i=0; i < values.filter_target.length; ++i) {
	            var oRecord = this.filters.getById(values['filter_target'][i]);
	            var target = oRecord.get('filter_column');
	            this.addActiveFilter({
	                target: target,
	                operator: values['filter_operator'][i],
	                value: values['filter_value'][i],
	                type: oRecord.get('filter_type'),
	                column: oRecord.get('filter_column'),
	                name: oRecord.get('filter_name') 
	            });
	        }
        }
        
        this.fireEvent('applyFilter', this.getFilters());
        
        this.oWin.hide();
    },
    
    addFilterTargetSelector: function() {
        return {
            xtype: 'combo',
            store: this.filters,
            mode: 'local',
            displayField: 'filter_name',
            valueField: 'filter_name',
            fieldLabel: _('Add restriction'),
            listeners: {
            	select: function(_this, oRecord, index) {
            		this.addFilter(_this.findParentByType(Ext.FormPanel), oRecord);	
            	},
            	scope: this
            }
        }
    },
    
    addFilterFields: function(filters) {
    	filters = filters || [];
    	this.show(true);
    	var parentCmp = this.oWin.findByType(Ext.FormPanel)[0];
    	if (filters.length) {
    		Ext.each(filters, function(filter) {
    			var _oRecord = Ext.data.Record.create([
    				{name: 'filter_type'},
    				{name: 'filter_name'},
    				{name: 'filter_operator'},
    				{name: 'filter_column'},
    				'filter_value'
    			]);
    			var oRecord = new _oRecord({
                    filter_type: filter.type,
                    filter_name: filter.name,
                    filter_operator: filter.operator,
                    filter_column: filter.column,
                    filter_value: filter.value
                });
    			
    			this.addFilter(parentCmp, oRecord);
    		}, this);
    	}
    },
    
    addFilter: function(parentCmp, oRecord) {
    	var opStore = new Ext.data.ArrayStore({
    		autoDestroy: true,
    		fields: ['id', 'label'],
    		data: this.operators[oRecord.get('filter_type')] || [],
    		autoLoad: true
    	});

    	var items = [{
    		xtype: 'textfield',
    		readOnly: true,
    		value: oRecord.get('filter_name'),
    		region: 'center',
    		name: 'filter_target',
    		width: 80
    	},{
    		xtype: 'combo',
    		store: opStore,
    		allowBlank: false,
    		valueField: 'id',
    		displayField: 'label',
    		value: oRecord.get('filter_operator') || opStore.getAt(0).get('id') || '',
    		name: 'filter_operator',
    		mode: 'local',
    		width: 100
    	},{
    		xtype: 'combo',
    		store: new Ext.data.JsonStore({
    			autoDestroy: true,
    			url: this.config.url,
    			fields: [oRecord.get('filter_column')],
    			root: 'events',
    			baseParams: {
    				'target': oRecord.get('filter_target'),
    				'group_by': oRecord.get('filter_column'),
    				'column': oRecord.get('filter_column')
    			},
    			autoLoad: true
    		}),
    		displayField: oRecord.get('filter_column'),
    		valueField: oRecord.get('filter_column'),
    		allowBlank: false,
    		triggerAction: 'all',
    		name: 'filter_value',
    		mode: 'remote',
    		value: oRecord.get('filter_value') || ''
    	},{
            xtype: 'button',
            iconCls: 'icinga-icon-cross',
            handler: function(_this, e) {
            	var toRemove = _this.findParentByType('panel');
            	parentCmp.remove(toRemove);
            	parentCmp.doLayout();
            }
    	}];

    	panel = new Ext.Panel({
    		layout: 'column',
    		border: false,
    		defaults: {
    			border: false,
    			style: 'padding: 2px;'
    		},
    		items: items
    	});

    	parentCmp.add(panel);
    	parentCmp.doLayout();
    },
    
    show: function(renderOnly) {
    	renderOnly = renderOnly || false;
    	if (!this.oWin) {
    		this.oWin = new Ext.Window({
                layout: 'fit',
                closeAction: 'hide',
                region: 'center',
                width: 500,
                height: 300,
                title: _('Modify filter'),
                items: new Ext.FormPanel({
                	layout: 'fit',
                	items: this.addFilterTargetSelector()
                }),
                bbar: {
                    defaults: {
                        scope: this
                    },
                    items: [{
                        text: _('Apply'),
                        iconCls: 'icinga-icon-accept',
                        handler: function(oButton, e) {
                        	var fp = this.oWin.findByType(Ext.FormPanel)[0];
                        	var f = fp.getForm();
                        	if(f.isValid()) {
                        		var values = f.getFieldValues();
                        		this.applyFilter(values);
                        	}     
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