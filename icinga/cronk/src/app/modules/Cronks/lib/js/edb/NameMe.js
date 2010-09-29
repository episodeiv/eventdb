Ext.ns('Cronk.EventDB');

Cronk.EventDB.NameMe = {
		
	hostCronk: function(cfg) {
		return function(grid, rowIndex, colIndex, e) {
			var fieldName = grid.getColumnModel().getDataIndex(colIndex);
			if (fieldName == cfg.field) {
				var title = Cronk.grid.ColumnRendererUtil.applyXTemplate(grid, rowIndex, cfg.title);
				var host = Cronk.grid.ColumnRendererUtil.applyXTemplate(grid, rowIndex, cfg.host);
				var tabPanel = Ext.getCmp('cronk-tabs');
				var hostCronk = Ext.getCmp('event_db-host') || false;
				var isAvailable = true;
				if (!hostCronk) {
					var hostCronk = tabPanel.add({
						'xtype': 'cronk',
						'crname': 'icingaEventDB',
						'closable':true,
						'title': title || 'EventDB',
						'id': 'event_db-host'
					});
					tabPanel.doLayout();
					isAvailable = false;
				}
				hostCronk.hostFilter = host;
				if (isAvailable) {
					eventDB = hostCronk.items.first();
					eventDB.applyHostFilter();
					hostCronk.setTitle('EventDB for ' + host);
				}
				tabPanel.setActiveTab(hostCronk);
			}
		};
	}
};