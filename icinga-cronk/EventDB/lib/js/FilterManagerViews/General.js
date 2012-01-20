

Ext.ns('Cronk.EventDB.FilterManagerViews').General = function() {
	var ev_id =  Ext.id('panel_filter');
	return new (Ext.extend(Ext.FormPanel,{
		// extend
		autoDestroy: false,
		updateFields: function(vals) {
			function cycleSearch(item) {
				return item.value == this.toSearch;
			}
			for(var i in vals) {
				switch(i) {
					case 'sourceExclusion':
						var checkboxes = Ext.getCmp('chksrc_'+ev_id);
						var checkvals = checkboxes.getValue();
						Ext.iterate(vals[i],function(nr) {
							checkvals[nr] = true;
						});

						checkboxes.setValue(checkvals);
						break;
					case 'facilityExclusion':
						var checkboxes = Ext.getCmp('fac_chkbx_'+ev_id);
						var checkvals = checkboxes.getValue();
						Ext.iterate(vals[i],function(nr) {
							checkvals[nr] = true;
						});
						checkboxes.setValue(checkvals);
						break;
					case 'priorityExclusion':
						var checkboxes = Ext.getCmp('chkbx_'+ev_id);
						var checkvals = checkboxes.getValue();

						Ext.iterate(vals[i],function(nr) {
							checkvals[nr] = true;
						});
						checkboxes.setValue(checkvals);
						break;
					case 'misc':
						var checkboxes = Ext.getCmp('misc_'+ev_id);

						if(vals[i].hideAck) {
							checkboxes.setValue([true]);
						}
						break;
					case 'timespan':
						var t;
						if((vals[i].from || -1) != -1) {
							t = new Date();
							t.setTime(vals[i].from*1000);
							var d_from = Ext.getCmp('date_from_'+ev_id);
							var t_from = Ext.getCmp('time_from_'+ev_id);
							d_from.enable();
							t_from.enable();
							d_from.setValue(t);
							t_from.setValue(t);
						}
						if((vals[i].to || -1) != -1) {
							t = new Date();
							t.setTime(vals[i].to*1000);
							var d_to = Ext.getCmp('date_to_'+ev_id);
							var t_to = Ext.getCmp('time_to_'+ev_id);
							d_to.enable();
							t_to.enable();
							d_to.setValue(t);
							t_to.setValue(t);
						}

						break;
					case 'display':
						if(vals[i].order) {
							for(var elem in vals[i].order) {
								var field = Ext.getCmp('order_'+elem+'_cycle_'+ev_id);
								this.toSearch = vals[i].order[elem];
								var it = field.menu.findBy(cycleSearch,this);
								if(it[0])
									field.setActiveItem(it[0]);
							}
						}
						if(vals[i].limit) {
							var field = Ext.getCmp('rpp_cycle_'+ev_id);
							this.toSearch = vals[i].limit;
							var it = field.menu.findBy(cycleSearch,this);
							if(it[0]);
								field.setActiveItem(it[0]);
						}
						break;
				}
			}
		}
	}))({
		layout: 'table',
		title: _('General'),
		layoutConfig: {
			columns: 3
		},
		defaults: {
			bodyStyle: {
				padding :2
			}
		},
		autoScroll: true,
		items: [{
			xtype: 'panel',
			layout: 'vbox',
			border: false,
			bodyStyle: {
				padding: 0
			},
			width: 160,
			height: 320,
			items: [{
				xtype: 'panel',
				title: _('Exclude message sources'),
				width: 160,
				height:160,
				tbar: [{
					xtype: 'button',
					text: _('All'),
					handler: function() {
						var chkBoxes = Ext.getCmp('chksrc_'+ev_id);
						for(var i=0;i<chkBoxes.items.items.length;i++) {
							var val = chkBoxes.items.items[i];
							val.setValue(true);
						}
					},
					scope: this
				}, {
					xtype: 'button',
					text: _('Clear'),
					handler: function() {
						var chkBoxes = Ext.getCmp('chksrc_'+ev_id);
						for(var i=0;i<chkBoxes.items.items.length;i++) {
							var val = chkBoxes.items.items[i];
							val.setValue(false);
						}
					}
				}, {
					xtype: 'button',
					text: _('Invert'),
					handler: function() {
						var chkBoxes = Ext.getCmp('chksrc_'+ev_id);
						for(var i=0;i<chkBoxes.items.items.length;i++) {
							var val = chkBoxes.items.items[i];
							val.setValue(!val.getValue())
						}
					}
				}],
				items: {
					xtype: 'checkboxgroup',
					columns: 1,
					name: 'sources',
					defaults: {
						xtype: 'checkbox'
					},
					id: 'chksrc_'+ev_id,
					items: [
						{boxLabel: 'Syslog',iconCls: 'eventdb_icon syslog', name: 'src_0'},
						{boxLabel: 'SNMP',iconCls: 'eventdb_icon smnp', name: 'src_1'},
						{boxLabel: 'Mail',iconCls: 'eventdb_icon mail', name: 'src_2'}
					]
				}
			},{
				xtype: 'panel',
				title: _('Misc'),

				width: 160,
				height: 160,
				items: {
					xtype: 'checkboxgroup',
					columns: 1,
					name: 'misc',
					id: 'misc_'+ev_id,
					defaults: {
						xtype: 'checkbox'
					},
					items : [
						{xtype: 'checkbox', boxLabel: _('Hide acknowledged'), name: 'misc_noAck'}
					]
				}
			}]
		},{
			layout: 'fit',
			xtype:'panel',
			title:_('Exclude Priorities:'),
			height:320,
			width:160,
			tbar: [{
				xtype: 'button',
				text: _('All'),
				handler: function() {
					var chkBoxes = Ext.getCmp('chkbx_'+ev_id);
					for(var i=0;i<chkBoxes.items.items.length;i++) {
						var val = chkBoxes.items.items[i];
						val.setValue(true);
					}
				},
				scope: this
			}, {
				xtype: 'button',
				text: _('Clear'),
				handler: function() {
					var chkBoxes = Ext.getCmp('chkbx_'+ev_id);
					for(var i=0;i<chkBoxes.items.items.length;i++) {
						var val = chkBoxes.items.items[i];
						val.setValue(false);
					}
				}
			}, {
				xtype: 'button',
				text: _('Invert'),
				handler: function() {
					var chkBoxes = Ext.getCmp('chkbx_'+ev_id);
					for(var i=0;i<chkBoxes.items.items.length;i++) {
						var val = chkBoxes.items.items[i];
						val.setValue(!val.getValue())
					}
				}
			}],
			items: [
			{
				xtype: 'checkboxgroup',
				columns: 1,
				name: 'priorities',
				id: 'chkbx_'+ev_id,
				defaults:{
					xtype: 'checkbox'
				},
				items: [
					{boxLabel:'Emergency',ctCls: 'eventdb_tag red', name:'prio_0'},
					{boxLabel:'Alert',ctCls: 'eventdb_tag orange', name:'prio_1'},
					{boxLabel:'Critical',ctCls: 'eventdb_tag orange', name:'prio_2'},
					{boxLabel:'Error',ctCls: 'eventdb_tag orange', name:'prio_3'},
					{boxLabel:'Warning',ctCls: 'eventdb_tag yellow', name:'prio_4'},
					{boxLabel:'Notice',ctCls: 'eventdb_tag green', name:'prio_5'},
					{boxLabel:'Info', name:'prio_6'},
					{boxLabel:'Debug', name:'prio_7'}
				]
			}]
		},{
			xtype: 'panel',
			title: _('Exclude facilities'),
			layout:'fit',
			width:400,
			height:320,
			tbar: [{
				xtype: 'button',
				text: _('All'),
				handler: function() {
					var chkBoxes = Ext.getCmp('fac_chkbx_'+ev_id);
					for(var i=0;i<chkBoxes.items.items.length;i++) {
						var val = chkBoxes.items.items[i];
						val.setValue(true);
					}
				},
				scope: this
			}, {
				xtype: 'button',
				text: _('Clear'),
				handler: function() {
					var chkBoxes = Ext.getCmp('fac_chkbx_'+ev_id);
					for(var i=0;i<chkBoxes.items.items.length;i++) {
						var val = chkBoxes.items.items[i];
						val.setValue(false);
					}
				}
			}, {
				xtype: 'button',
				text: _('Invert'),
				handler: function() {
					var chkBoxes = Ext.getCmp('fac_chkbx_'+ev_id);
					for(var i=0;i<chkBoxes.items.items.length;i++) {
						var val = chkBoxes.items.items[i];
						val.setValue(!val.getValue())
					}
				}
			},{
				xtype: 'button',
				text: _('Only local'),
				handler: function() {
					var chkBoxes = Ext.getCmp('fac_chkbx_'+ev_id);
					for(var i=0;i<chkBoxes.items.items.length;i++) {
						var val = chkBoxes.items.items[i];
						if(val.name.split("_")[1] > 15)
							val.setValue(true);
						else
							val.setValue(false);

					}
				}
			}],
			items: [
			new Ext.form.CheckboxGroup({
				name: 'facilities',
				xtype: 'checkboxgroup',
				columns: 1,
				autoScroll:true,
				height: 220,
				id: 'fac_chkbx_'+ev_id,
				defaults:{
					xtype: 'checkbox'
				},
				items: [

					{name: 'facility_0', id: 'facility_0'+ev_id, boxLabel : 'kernel messages'},
					{name: 'facility_1', id: 'facility_1'+ev_id, boxLabel : 'user-level messages'},
					{name: 'facility_2', id: 'facility_2'+ev_id, boxLabel : 'mail system'},
					{name: 'facility_3', id: 'facility_3'+ev_id, boxLabel : 'system daemons'},
					{name: 'facility_4', id: 'facility_4'+ev_id, boxLabel : 'security1'},
					{name: 'facility_5', id: 'facility_5'+ev_id, boxLabel : 'internal'},
					{name: 'facility_6', id: 'facility_6'+ev_id, boxLabel : 'line printer '},
					{name: 'facility_7', id: 'facility_7'+ev_id, boxLabel : 'network news '},
					{name: 'facility_8', id: 'facility_8'+ev_id, boxLabel : 'UUCP subsystem'},
					{name: 'facility_9', id: 'facility_9'+ev_id, boxLabel : 'clock daemon'},
					{name: 'facility_10', id: 'facility_10'+ev_id, boxLabel : 'security2'},
					{name: 'facility_11', id: 'facility_11'+ev_id, boxLabel : 'FTP daemon'},
					{name: 'facility_12', id: 'facility_12'+ev_id, boxLabel : 'NTP subsystem'},
					{name: 'facility_13', id: 'facility_13'+ev_id, boxLabel : 'log audit'},
					{name: 'facility_14', id: 'facility_14'+ev_id, boxLabel : 'log alert'},
					{name: 'facility_15', id: 'facility_15'+ev_id, boxLabel : 'clock daemon'},
					{name: 'facility_16', id: 'facility_16'+ev_id, ctCls: 'eventdb_tag local0', boxLabel : 'local0'},
					{name: 'facility_17', id: 'facility_17'+ev_id, ctCls: 'eventdb_tag local1', boxLabel : 'local1'},
					{name: 'facility_18', id: 'facility_18'+ev_id, ctCls: 'eventdb_tag local2', boxLabel : 'local2'},
					{name: 'facility_19', id: 'facility_19'+ev_id, ctCls: 'eventdb_tag local3', boxLabel : 'local3'},
					{name: 'facility_20', id: 'facility_20'+ev_id, ctCls: 'eventdb_tag local4', boxLabel : 'local4'},
					{name: 'facility_21', id: 'facility_21'+ev_id, ctCls: 'eventdb_tag local5', boxLabel : 'local5'},
					{name: 'facility_22', id: 'facility_22'+ev_id, ctCls: 'eventdb_tag local6', boxLabel : 'local6'},
					{name: 'facility_23', id: 'facility_23'+ev_id, ctCls: 'eventdb_tag local7', boxLabel : 'local7'}
				]
			})]
		},{
			xtype: 'panel',
			layout: 'form',
			width:320,
			labelWidth:50,
			colspan: 2,
			height:140,
			title: _('Timespan'),
			items: [{
				xtype: 'compositefield',
				fieldLabel: _('From'),
				layout:'form',
				name: 'timespan_from',
				getValue: function() {
					var _date = Ext.getCmp('date_from_'+ev_id).getValue();
					var _time = Ext.getCmp('time_from_'+ev_id).getValue();
					if(Ext.getCmp('date_from_'+ev_id).disabled)
						return -1;
					_time = _time.split(':');
					_date.setMinutes(_time[1]);
					_date.setHours(_time[0]);
					_date.setSeconds(_time[2]);

					return _date.getTime()/1000;
				},
				items: [{
					value: new Date(),
					xtype: 'datefield',
					id: 'date_from_'+ev_id,
					disabled: true,
					validateOnBlur:true,
					validator: function() {
						if(this.disabled)
							return true;
						var toDate = Ext.getCmp('date_to_'+ev_id);
						if(toDate.getValue()<this.getValue())
							return _("End-time must be later than start time");
						// re clearInvalid the other fields
						Ext.getCmp('date_from_'+ev_id).clearInvalid();
						Ext.getCmp('time_from_'+ev_id).clearInvalid();
						toDate.clearInvalid();

						return true;

					},
					width: 100
				}, {
					value: new Date(),
					xtype: 'timefield',
					id: 'time_from_'+ev_id,
					disabled: true,
					format: 'H:i:s',
					validateOnBlur:true,
					validator: function() {
						if(this.disabled)
							return true;
						var toDate = Ext.getCmp('date_to_'+ev_id);
						if(!toDate.isValid())
							return _("End-time must be later than start time");
						var fromDate = Ext.getCmp('date_from_'+ev_id);
						var toTime = Ext.getCmp('time_to_'+ev_id);
						if(fromDate.getValue() == toDate.getValue()
								&& toTime.getValue() < this.getValue())
							return _("End-time must be later than start time");
						fromDate.clearInvalid();
						toDate.clearInvalid();
						toTime.clearInvalid();

						return true;
					},
					width: 100
				}, {
					xtype: 'button',
					text: _('Ignore'),
					enableToggle: true,
					pressed: true,
					toggleHandler: function(btn, state) {
						btn.reset = function() {

							btn.toggle(btn.initConfig.pressed);
						}
						for(var i=0;i<btn.ownerCt.items.length;i++) {
							var field = btn.ownerCt.items.items[i];
							if(field.initialConfig.xtype != 'button') {
								if(state)
									field.disable();
								else
									field.enable();
							}
						}
					}
				}]
			},{
				xtype: 'compositefield',
				fieldLabel: _('To'),
				name: 'timespan_to',
				getValue: function() {
					var _date = Ext.getCmp('date_to_'+ev_id).getValue();
					var _time = Ext.getCmp('time_to_'+ev_id).getValue();
					if(Ext.getCmp('date_to_'+ev_id).disabled)
						return -1;
					_time = _time.split(':');
					_date.setMinutes(_time[1]);
					_date.setHours(_time[0]);
					_date.setSeconds(_time[2]);

					return _date.getTime()/1000;
				},
				items: [{
					value: new Date(),
					xtype: 'datefield',
					id: 'date_to_'+ev_id,
					disabled: true,
					width: 100,
					validateOnBlur: true,
					validationDelay: 500,
					validator: function() {
						if(this.disabled)
							return true;
						var fromDate = Ext.getCmp('date_from_'+ev_id);
						if(fromDate.getValue()>this.getValue())
							return _("End-time must be later than start time");
						fromDate.clearInvalid();
						Ext.getCmp('date_to_'+ev_id).clearInvalid();
						Ext.getCmp('time_from_'+ev_id).clearInvalid();

						return true;
					}
				}, {
					value: new Date(),
					xtype: 'timefield',
					id: 'time_to_'+ev_id,
					disabled : true,
					validateOnBlur:true,
					format: 'H:i:s',
					validator: function() {
						if(this.disabled)
							return true;
						var fromDate = Ext.getCmp('date_from_'+ev_id);
						if(!fromDate.isValid())
							return _("End-time must be later than start time");
						var toDate = Ext.getCmp('date_to_'+ev_id);
						var fromTime = Ext.getCmp('time_from_'+ev_id);
						if(fromDate.getValue() >= toDate.getValue()
								&& fromTime.getValue() > this.getValue())
							return _("End-time must be later than start time");
						fromDate.clearInvalid();
						toDate.clearInvalid();
						fromTime.clearInvalid();

						return true;
					},
					width: 100
				}, {
					xtype: 'button',
					text: _('Ignore'),
					enableToggle: true,
					pressed: true,
					toggleHandler: function(btn, state) {

						for(var i=0;i<btn.ownerCt.items.length;i++) {
							var field = btn.ownerCt.items.items[i];
							if(field.initialConfig.xtype != 'button') {
								if(state)
									field.disable();
								else
									field.enable();
							}
						}
					},
					scope :this
				}]
			}]
		},{
			xtype: 'panel',
			title: _('Default options'),
			rowspan: 2,
			width: 400,
			height: 140,
			layout: 'form',
			items: [{
				showText: true,
				name: 'rpp',
				xtype: 'cycle',
				id: 'rpp_cycle_'+ev_id,
				width:25,
				fieldLabel: _('Records per page'),
				items: [{
					text: '25',
					value: 25,
					checked: true
				},{
					text: '50',
					value: 50
				},{
					text: '100',
					value: 100
				},{
					text: '200',
					value: 200
				}]
			},{
				showText: true,
				xtype: 'cycle',
				width: 25,
				name: 'order_by',
				id: 'order_field_cycle_'+ev_id,

				fieldLabel: _('Order by'),
				items: [{
					text: _('Create time'),
					value: 'created'
				},{
					text: _('Last modified'),
					value: 'modified'
				},{
					text: _('Host name'),
					value: 'host_name'
				},{
					text: _('Event priority'),
					value: 'priority'
				},{
					text: _('Event facility'),
					value: 'facility'
				},{
					text: _('Acknowledged'),
					value: 'ack'
				}]
			},{
				showText: true,
				xtype: 'cycle',
				width: 35,
				name: 'order_dir',
				id: 'order_dir_cycle_'+ev_id,
				fieldLabel: _('Search order'),
				items: [{
					text: _('desc'),
					selected: true,
					value: 'desc'
				}, {
					text: _('asc'),
					value: 'asc'
				}]
			},{
				showText: true,
				hidden:true,
				xtype: 'cycle',
				name: 'group_by',
				width: 35,
				fieldLabel: _('Group by'),
				items: [{
					text: _('None'),
					value: null
				},{
					text: _('Host name'),
					value: 'host_name'
				},/*{
					text: _('Event priority'),
					value: 'priority'
				},{
					text: _('Event facility'),
					value: 'facility'
				},*/{
					text: _('Acknowledged'),
					value: 'ack'
				}]
			}]
		}]
	});
}