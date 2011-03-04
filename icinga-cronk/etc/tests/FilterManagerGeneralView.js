Ext.ns({Cronk.EventDB.FilterManager})
Cronk.EventDB.FilterManager.GeneralView = function() {

	return new Ext.FormPanel({
		layout: 'table',
		title: _('General'),
		layoutConfig: {
			columns: 3
		},
		items: [{
			layout:'form',	
			title: _('Hosts'),	
			width:320,
			rowspan:2,
			height:500,
			items: [{
				
				width:300,
				height:75,
				xtype: 'panel',
				layout :'form',	
				defaults: {
					border:false
				},
				
				items: this.formBuilder.createTextIncludeExcludeField('Host')
			},{			
				xtype: 'panel',
				
				items: 	[{
					layout: 'column',
					width: '100%',
					height:300,
					items: [{
						columnWidth: .5,
						layout:'fit',
						xtype: 'panel',
						tools: [{
							id: 'plus'
						},{
							id: 'minus'
						}],
						title: _('Included hosts')
					},{
						columnWidth: .5,
						layout:'fit',
						xtype: 'panel',	
						tools: [{
							id: 'plus'
						},{
							id: 'minus'
						}],
						title: _('Excluded hosts')
					}]
				}]
			},{
				xtype:'panel',
				html: '% Hosts selected'
			}]
		},{
			layout:'form',	
			title: _('Programs'),	
			rowspan:2,
			width:320,
			height:500,
			items: [{
				
				width:300,
				height:75,
				xtype: 'panel',
				layout :'form',	
				defaults: {
					border:false
				},
				
				items: this.formBuilder.createTextIncludeExcludeField('Program')
			},{			
				xtype: 'panel',
				
				items: 	[{
					layout: 'column',
					width: '100%',
					height:300,
					items: [{
						columnWidth: .5,
						layout:'fit',
						xtype: 'panel',
						tools: [{
							id: 'plus'
						},{
							id: 'minus'
						}],
						title: _('Included programs')
					},{
						columnWidth: .5,
						layout:'fit',
						xtype: 'panel',	
						tools: [{
							id: 'plus'
						},{
							id: 'minus'
						}],
						title: _('Excluded programs')
					}]
				}]
			},{
				xtype:'panel',
				html: '% Programs selected'
			}]
		},{
		
			width: 140,
			xtype: 'panel',
			title: _('Exclude facilities'),
			layout:'fit',
			height:220,
			items: [
			{ 
				xtype: 'checkboxgroup',	
				columns: 1,
				autoScroll:true,
				height: 220,
				defaults:{
					xtype: 'checkbox'
				},
				items: [
					{name: 'facility_1', boxLabel : 'user-level messages'},
					{name: 'facility_2', boxLabel : 'mail system'},
					{name: 'facility_3', boxLabel : 'system daemons'},
					{name: 'facility_4', boxLabel : 'security1'},
					{name: 'facility_5', boxLabel : 'internal'},
					{name: 'facility_6', boxLabel : 'line printer '},
					{name: 'facility_7', boxLabel : 'network news '},
					{name: 'facility_8', boxLabel : 'UUCP subsystem'},
					{name: 'facility_9', boxLabel : 'clock daemon'},
					{name: 'facility_10', boxLabel : 'security2'},
					{name: 'facility_11', boxLabel : 'FTP daemon'},
					{name: 'facility_12', boxLabel : 'NTP subsystem'},
					{name: 'facility_13', boxLabel : 'log audit'},
					{name: 'facility_14', boxLabel : 'log alert'},
					{name: 'facility_15', boxLabel : 'clock daemon'},
					{name: 'facility_16', boxLabel : 'local0'},
					{name: 'facility_17', boxLabel : 'local1'},
					{name: 'facility_18', boxLabel : 'local2'},
					{name: 'facility_19', boxLabel : 'local3'},
					{name: 'facility_20', boxLabel : 'local4'},
					{name: 'facility_21', boxLabel : 'local5'},
					{name: 'facility_22', boxLabel : 'local6'},
					{name: 'facility_23', boxLabel : 'local7'}
				]	   
			}]	
		},{
			layout: 'fit',
			xtype:'panel',
			title:_('Exclude Priorities:'),
			height:230,	
			items: [
			{ 
				xtype: 'checkboxgroup',	
				columns: 1,
				defaults:{
					xtype: 'checkbox'
				},
				items: [ 
					{boxLabel:'Emergency', name:'prio_0'},
					{boxLabel:'Alert', name:'prio_1'},
					{boxLabel:'Critical', name:'prio_2'},
					{boxLabel:'Error', name:'prio_3'},
					{boxLabel:'Warning', name:'prio_4g'},
					{boxLabel:'Notice', name:'prio_5'},
					{boxLabel:'Info', name:'prio_6'},
					{boxLabel:'Debug', name:'prio_7'}
				]	   
			}],	
		}]
	});
}
