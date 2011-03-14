Ext.ns('Cronk.EventDB')
Ext.ns("Cronk.EventDB.FilterManagerViews");

Cronk.EventDB.FilterManagerViews.Advanced = function(url) {
	var handler = {
		addPopup: function(field,targetStore,excludeStore) {
			var store = new Ext.data.JsonStore({
				url: url,
				root: 'events',
				fields: [
					{name: field}
				],
				baseParams: {
					'group_by' : field,
					'columns[0]' : field,	
					'count' : field
				},
				defaultParamNames: {
					start: 'offset',
					limit: 'limit',
					sort: 'order',
					dir: 'order'
				},
				totalProperty: 'count',
				listeners: {
					beforeload: function() {
						var toFilter = [];
						excludeStore.each(function(val) {
							toFilter.push(val.get(field));
						});

						targetStore.each(function(val) {
							toFilter.push(val.get(field));
						});
						if(toFilter.join('|')) {
							store.setBaseParam('filter[1][target]',field);
							store.setBaseParam('filter[1][operator]', 'NOT IN');
							store.setBaseParam('filter[1][value]',toFilter.join('|'));
						}
					}
				}	
			});
			var pBar = new Ext.PagingToolbar({
				store: store,
				displayInfo: true,
				pageSize:25,
				prependButtons: true,	
				items: [
				{
					xtype: 'button',
					text: _('Add'),
					iconCls: 'icinga-icon-add',
					handler: function(btn) {
						var grid = btn.ownerCt.ownerCt; //toolbar->panel
						if(grid.getSelectionModel().getCount() == 0)
							return false;
						var sel = grid.getSelectionModel().getSelections();
						if(sel) {
							targetStore.add(sel);
							pBar.doRefresh();
						}
					},
					scope: this
				}
				]
			})
			var tBar = [{
				html: _('Contains ')
			},{
				xtype: 'textfield',
				fieldLabel: _('Contains'),
				listeners: {
					change: function(btn) {
						store.setBaseParam('filter[0][target]',field),
						store.setBaseParam('filter[0][operator]',60),
						store.setBaseParam('filter[0][value]',"%"+btn.getValue()+"%")
						pBar.doRefresh();
					},
					scope: this
				}
			}];

			var popup = new Ext.Window({
				resizeable: false,
				minimizible: false,
				title: _('Select'),
				width:400,
				height:400,
				layout:'fit',	
				autoDestroy: true,	
				items: new Ext.grid.GridPanel({
					store: store,
					columns: [{
						header: field, dataIndex: field	
					}],
					bbar: pBar,
					tbar: tBar,

					viewConfig: {
						forceFit: true	
					}
				})			
				
			});
			popup.show();	
			pBar.doRefresh();
		},

		addFilterMsgRow: function(elem,values) {
			var presets = values || {};
			
			var ct = elem.add ? elem :  elem.ownerCt.ownerCt; //toolbar->panel	
			var id = Ext.id('row');
			var row = new Ext.form.CompositeField({
				xtype: 'compositefield',
				width: 600,
				id: 'msgrow_'+id,
				name: 'Message_filter',	
				labelSize: 0,
				items: [{
					showText: true,	
					xtype: 'cycle',
					id: 'type_'+id,
					items: [{
						selected:  presets['type'] == 'inc',
						text: _('Include'),
						value: 'inc'
					}, {
						selected:  presets['type'] == 'exc',
						text: _('Exclude'),
						value: 'exc'
					}]
				},{
					width: 300,
					xtype: 'textfield',
					value: values ? presets['message']  : '',
					id: 'msg_'+id
				},{
					xtype: 'checkbox',
					checked: values ? presets['isRegexp'] : false,

					id: 'regexp_'+id,
					boxLabel: 'RegExp'
				},{
					xtype: 'spacer',
					width:20
				},{
					xtype: 'button',
					text: _('Remove'),
					handler: function() {
						Ext.getCmp('msgrow_'+id).ownerCt.remove(Ext.getCmp('msgrow_'+id));	
					} 
				}]
			});
			row.getValue= function() {
				if(!Ext.getCmp('type_'+id))
					return null;
				return { 
					type : Ext.getCmp('type_'+id).getActiveItem().value,
					message: Ext.getCmp('msg_'+id).getValue(),
					isRegexp: Ext.getCmp('regexp_'+id).checked
				}
			}
			ct.add(row);	
			ct.doLayout();
			var r = ct.reset;
			var g;
			if(ct.getValue)
				g = ct.getValue;
			ct.getValue = function(set) {
				var set = set || [];
				
				if(!row.hidden) {
					set.push(row.getValue());
				}
				
				if(g)
					return g(set);
			}
			ct.reset = function() {
				if(row) {
					ct.remove(row);
					row.hide();
				}
				if(r) 
					r();
			}
		}
		
	}
	
	this.formBuilder = new function() {	
		this.inclExclGrid = function(name,fieldname) {
			var incstore = new Ext.data.ArrayStore({
				autoDestroy: true,
				idIndex: 0,
				fields: [fieldname]		
			});
			var excstore = new Ext.data.ArrayStore({
				autoDestroy: true,
				idIndex: 0,
				fields: [fieldname]		
			});
			
			var incgrid = new Ext.grid.GridPanel({
				store: incstore,
				columns: [{header: name,dataIndex:fieldname}],
				viewConfig: {forceFit: true}
			});	
			var excgrid = new Ext.grid.GridPanel({
				store: excstore,
				columns: [{header: name,dataIndex:fieldname}],
				viewConfig: {forceFit: true}
			});	
			incgrid.reset = function() {
				incstore.removeAll();	
			}
			excgrid.reset = function() {
				excstore.removeAll();	
			}
			incgrid.getValue = function() {
				var set = [];
				incstore.each(function(r) {
					set.push(r.get(fieldname));	
				});
				return set;
			}
			incgrid.name = name+'_Include_set';
			excgrid.name = name+'_Exclude_set';
			excgrid.getValue = function() {
				var set = [];
				excstore.each(function(r) {
					set.push(r.get(fieldname));	
				});
				return set;
			}
			return [{
				setIncludes: function(data) {	
					if(data.length > 0) {
						for(var i=0;i<data.length;i++)
							data[i] =[data[i]]
			
					}
					incstore.loadData(data);	
				
				},
				setExcludes: function(data) {
					if(data.length > 0)	{
						for(var i=0;i<data.length;i++)
							data[i] =[data[i]]
					
					}
					excstore.loadData(data)
				},
				height:150,
				columnWidth: .5,
				layout:'fit',
				xtype: 'panel',
				tools: [{
					id: 'plus',
					handler: handler.addPopup.createDelegate(this,[fieldname,incstore,excstore]),
					scope: this
				},{
					id: 'minus',
					handler: function(btn,arg,panel) {
					
						var grid = panel.items.items[0];
						var selected = grid.getSelectionModel().getSelections();
						incstore.remove(selected);
					}
				}],
				title: _('Additional '+name+"s"),
				items: incgrid

			},{
				height:150,
				columnWidth: .5,
				layout:'fit',
				xtype: 'panel',	
				tools: [{
					id: 'plus',
					handler: handler.addPopup.createDelegate(this,[fieldname,excstore,incstore]),
					scope: this
				},{
					id: 'minus',
					handler: function(btn,arg,panel) {
						var grid = panel.items.items[0];
					
						var selected = grid.getSelectionModel().getSelections();
						excstore.remove(selected);
					}
				}],
				title: _('Excluded '+name+'s'),
				items: excgrid
			}]
		}

		this.createTextIncludeExcludeField = function(name,store) {
			var impl = new function() {
				this.name = name;
				this.store = this.store;
				this.evId = Ext.id();
			
				this.createFieldset = function(includeOrExclude) {
					var btns = {
						showText: true,
						xtype: 'cycle',
						id: 'form_btn_'+includeOrExclude+'_'+this.name+'_'+this.evId,
						name: name+"_"+includeOrExclude+"_pattern_type",
						reset: function() {	
							for(var i=0;i<this.items.length;i++) {
								if(this.items[i].selected)
									this.menu.items.itemAt(i).setChecked(true);
							}	
						},
						items: [{
							selected: true,
							text: _('Disabled'),
							value: "disabled"
						},{
							text: _('Exact'),
							value: "exact"
						},{
							text: _('Contains'),
							value: "contains"
						},{
							text: _('RegExp'),
							value: "regexp"
						}],
						listeners: {
							change: this.onToggle,
							scope: this
						},
						selType: includeOrExclude
					}

					return {
						layout:'column',
						border:false,
						width:300,
						items: [{
							layout:'form',
							id: 'form_'+includeOrExclude+'_'+this.name+'_'+this.evId,
							labelWidth: 60,
							disabled: true,
							
							border:false,
							items: [{
								xtype: 'textfield',		
								fieldLabel: _(includeOrExclude),
								id: 'form_txt_'+includeOrExclude+'_'+this.name+'_'+this.evId,
								name: name+"_"+includeOrExclude+'_pattern'
							}] 
						},{
							width:100,
							layout: 'column',	
							border: false,
							items: btns	
						}]
					}
				}

				this.onToggle = function(btn,selected) {
					var includeOrExclude = btn.selType;
					if(!includeOrExclude)
						return true;

					var ev_uid = this.evId;	
					var id = 'form_'+includeOrExclude+'_'+this.name+'_'+this.evId;	
					var incForm = Ext.getCmp(id);
					incForm.matchType = selected.value;
					if(incForm)
						selected.value == 'disabled' ? incForm.disable() : incForm.enable();
				}
			}

			return {
				setValue: function(t,type,inc) {
					
					var txt_id = 'form_txt_'+inc+'_'+impl.name+'_'+impl.evId;	
					var btn_id = 'form_btn_'+inc+'_'+impl.name+'_'+impl.evId;	

					var txtform = Ext.getCmp(txt_id);
					var btnform = Ext.getCmp(btn_id);
					if(t)
						txtform.setValue(t);
					btnform.menu.cascade(function(entry) {
						if(entry.value == type) {
							btnform.setActiveItem(entry);
							return true;
						}
					});
	
				},
				setIncludeValue: function(t,type) {	
					this.setValue(t,type,"Include");
				},
				setExcludeValue: function(t,type) {
					this.setValue(t,type,"Exclude");
				},
				layout:'form',
				border:false,
				style: 'padding:5px',		
				width:'100%',	
				items:  [impl.createFieldset('Include'),impl.createFieldset('Exclude')]
			};	
		}	
	}
	var hostPatternField = this.formBuilder.createTextIncludeExcludeField('Host');
	var hostIncludeExcludeField = this.formBuilder.inclExclGrid("Host","host_name");	
	var programPatternField = this.formBuilder.createTextIncludeExcludeField('Program');
	var programIncludeExcludeField = this.formBuilder.inclExclGrid("Program","program");

	return new (Ext.extend(Ext.FormPanel,{
		// extend
		updateFields: function(vals) {
			var tmp;
			for(var i in vals) {
				switch(i) {
					case 'hostFilter':
					case 'programFilter':	
						var target;
						if(i == "hostFilter")
							target = {p: hostPatternField, i:hostIncludeExcludeField}
						else
							target = {p: programPatternField, i:programIncludeExcludeField}
						tmp = vals[i];
						target.p.setIncludeValue(tmp.include_pattern,tmp.include_pattern_type);
						target.p.setExcludeValue(tmp.exclude_pattern,tmp.exclude_pattern_type);
						target.i[0].setIncludes(tmp.include_set);
						target.i[0].setExcludes(tmp.exclude_set);
						break;
					case 'messageFilter':						
						this.items.items[2].removeAll();
						for(var x =0;x<vals[i].items.length;x++)
							handler.addFilterMsgRow(this.items.items[2],vals[i].items[x]);
						break;
				}
			}
		}	
	}))({
		//invoke
		layout: 'table',
		title: 'Advanced',
		layoutConfig:{
			columns: 2
		},
		items: [{
			
			layout:'form',	
			title: _('Hosts'),
			width: 400,	
			items: [{
				
				width:400,
				height:75,
				xtype: 'panel',
				layout :'form',	
				defaults: {
					border:false
				},
				
				items: hostPatternField 
			},{			
				xtype: 'panel',	
				items: 	[{
					layout: 'column',	
					items: hostIncludeExcludeField
				}]
			}]
		},{
			layout:'form',	
			title: _('Programs'),		
			width:400,
			items: [{
				width:400,
				height:75,
				xtype: 'panel',
				layout :'form',	
				defaults: {
					border:false
				},
				items: programPatternField
			},{			
				xtype: 'panel',	
				items: 	[{
					layout: 'column',	
					items: programIncludeExcludeField 
				}]
			}]
		},{
			xtype: 'panel',
			title: _('Filter by message'),
			layout: 'form',
			bodyStyle: {
				padding:4	
			},
			tbar: [{
				text: 'Add',
				handler: handler.addFilterMsgRow,
				scope: this
			}],
			height: 200,
			autoScroll:true,
			colspan: 2
		}]
	})
}

Cronk.EventDB.FilterManagerViews.General = function() {
	var ev_id=  Ext.id('panel_filter');
	return new (Ext.extend(Ext.FormPanel,{
		// extend
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
						if((vals[i].from || -1) != -1) {
							t = new Date();
							t.setTime(vals[i].from*1000);
							var d_from = Ext.getCmp('date_from_'+ev_id);
							var t_from = Ext.getCmp('time_from_'+ev_id);
							d_from.enable();
							t_from.enable();
							d_from.setValue(t);
							t_from.setValue(t);
						}
						if((vals[i].to || -1) != -1) {
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
						if(vals[i].limit) {
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
						{boxLabel: 'SMNP',iconCls: 'eventdb_icon smnp', name: 'src_1'},
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
						if(val.name.split("_")[1] > 15)
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
					text: _('Last modified'),
					value: 'modified'
				},{
					text: _('Create time'),
					value: 'created'
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

Cronk.EventDB.FilterManager = Ext.extend(Ext.util.Observable, {
	constructor: function(cfg) {
		
		Ext.apply(this,cfg);
		Ext.util.Observable.prototype.constructor.call(this,cfg);
	},
	oWin: null,
	
	config: {},
	
	activeFilters: {},
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
	},
	
	getFilterDescriptor: function() {	
		if(!this.oWin) {
			return this.defaultValues;
		}
		var descriptor = {
			hostFilter: {
				include_pattern: false,	
				include_pattern_type: 'disabled',
				exclude_pattern_type: 'disabled',
				exclude_pattern: false,
				include_set: [],
				exclude_set: []
			},
			programFilter: {
				include_pattern: false,
				include_pattern_type: 'disabled',
				exclude_pattern: false,
				exclude_pattern_type: 'disabled',
				include_set: [],
				exclude_set: []
			},
			messageFilter: {
				items: []
			},
			misc: {
				hideAck: false	
			},
			sourceExclusion: [],
			priorityExclusion: [],
			facilityExclusion: [],
			timespan: {
				from: -1,
				to: -1 
			},
			display: {
				order: {
					field: '',
					dir: 'desc'
				},
				group: {
					field: null
				},
				count: 'id',	
				limit: 25
			}
		}
		this.oWin.cascade(function(elem) {
			if(elem.getValue)
				var val = elem.getValue();
				if(!val)
					return true;
				switch(elem.name) {
					case 'misc':
						for(var i=0;i<val.length;i++) {
							if(val[i].name.split('_')[1] == 'ack');
								descriptor.misc.hideAck = val[i].checked;
						}
						break;
					case 'priorities':
						for(var i=0;i<val.length;i++) {
							descriptor.priorityExclusion.push(val[i].name.split('_')[1]);	
						}
						break;
					case 'facilities':
						for(var i=0;i<val.length;i++) {
							descriptor.facilityExclusion.push(val[i].name.split('_')[1]);	
						}
						break;
					case 'timespan_from':
						descriptor.timespan.from = val;
						break;
					case 'timespan_to':
						descriptor.timespan.to = val;
						break;
					case 'sources':
						for(var i=0;i<val.length;i++) {
							descriptor.sourceExclusion.push(val[i].name.split('_')[1]);	
						}
					
						break
					case 'Host_Include_set':
						descriptor.hostFilter.include_set = val;
						break;
					case 'Host_Exclude_set':
						descriptor.hostFilter.exclude_set = val;
						break;
					case 'Host_Include_pattern':	
						descriptor.hostFilter.include_pattern = val;
						descriptor.hostFilter.include_pattern_type = elem.ownerCt.matchType;
						break;
					case 'Host_Exclude_pattern':
						descriptor.hostFilter.exclude_pattern = val;
						descriptor.hostFilter.exclude_pattern_type = elem.ownerCt.matchType;
						break;
					case 'Program_Include_set':
						descriptor.programFilter.include_set = val;
						break;
					case 'Program_Exclude_set':
						descriptor.programFilter.exclude_set = val;
						break;
					case 'Program_Include_pattern':
						descriptor.programFilter.include_pattern = val;
						descriptor.programFilter.include_pattern_type = elem.ownerCt.matchType;
						break;
					case 'Program_Exclude_pattern':
						descriptor.programFilter.exclude_pattern = val;
						descriptor.programFilter.exclude_pattern_type = elem.ownerCt.matchType;
						break;
					case 'Message_filter':
						descriptor.messageFilter.items.push(val);
						break;
				}
		});

		var cycleBtns = this.oWin.findByType('button'); 
		for(var i=0;i<cycleBtns.length;i++) {
			var val = cycleBtns[i].getActiveItem().value;
			switch(cycleBtns[i].name) {
				case 'rpp':
					descriptor.display.limit = val;
					break;
				case 'order_by':
					descriptor.display.order.field = val;
					break;
				case 'order_dir':
					descriptor.display.order.dir = val;
					break;
				case 'group_by':
					descriptor.display.group.field = val;
					break;
			}	
		}
	
		this.defaultValues = descriptor;
		return descriptor;	
	},
	setFilterValues: function(descriptor) {
		if(!Ext.isObject(descriptor)) {
			AppKit.log("Invalid filter provided");
			return true;
		}
		this.defaultValues = descriptor;	
		//this.fireEvent('applyFilter',descriptor);
	},
	defaultValues: {},
	generalView: Cronk.EventDB.FilterManagerViews.General(),
	advancedView: null, 
    show: function(renderOnly) {
		if(!this.advancedView)	
			this.advancedView = Cronk.EventDB.FilterManagerViews.Advanced(this.url);
		
		var ev_uid = Ext.id('eventdb_filterwin');
    	renderOnly = renderOnly || false;
		
		this.generalView.updateFields(this.defaultValues);
		this.advancedView.updateFields(this.defaultValues);
    	
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
                        	var desc = this.getFilterDescriptor();
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
