Ext.ns('Cronk.grid.ColumnRenderer');
(function() {
	/**
	 * The column render task, collects columns via DOM Query and aggregates their ids
	 * @param {Object} cfg  The meta data provided by the template
	 * 
	 * @author jmosshammer<jannis.mosshammer@netways.de>
	 */
	var edbColumnSelector = new Ext.util.DelayedTask(function(cfg,record) {
		var objsToCheck = (Ext.DomQuery.jsSelect("div.edb_cronk_sel"));
		var idsToCheck = [];
		cfg.elems = {};
		Ext.each(objsToCheck,function(obj) {
			var elem = Ext.get(obj);
			var obj_id = elem.getAttribute('edb_val');
			idsToCheck.push(obj_id);
			if(!cfg.elems[obj_id])
				cfg.elems[obj_id] = elem;
			else if(!Ext.isArray(cfg.elems[obj_id]))
				cfg.elems[obj_id] = [cfg.elems[elem.getAttribute('edb_val')],elem];
			else 
				cfg.elems[obj_id].push(elem);
		},this);
		requestEdbElements(idsToCheck,cfg,drawLinks,this);
	});

    var buildFilter = function(cfg) {
        var cv = {
            "type": 'atom',
            "field": [cfg.type+'_CUSTOMVARIABLE_NAME'],
            "method": ['='],
            "value": ['EDB_FILTER']
        };
        if(cfg.cv_filter) {
            cv = {
                "type": 'OR',
                'field' : [
                    cv,
                    {
                        "type": 'atom',
                        "field": [cfg.type+'_CUSTOMVARIABLE_NAME'],
                        "method": ["="],
                        "value" : [cfg.cv_filter]
                    }
                ]
            }
        }
        return cv;

    }

	/**
	 * Requests EventDB customvariables for the shown hosts/services in order to draw the links
	 * into the grid
	 * @param {Array}ids  The ids for which to request eventdbs
	 * @param {Array} cfg  Object containing 
	 * 						  - base: The basename of the icinga url, most likely icinga-web
	 * 						  - target: The target column, service/hostname
	 * 						  - type : 'SERVICE' or 'HOST'
	 * @author jmosshammer<jannis.mosshammer@netways.de>
	 */
	var requestEdbElements = function(ids,cfg,callback,scope) {
		if(!Ext.isArray(ids))
			return false;
		var idJSON = {
			type: 'OR',
			field: []
		};
		
		Ext.iterate(ids,function(id){
			idJSON.field.push({type:'atom',field: [cfg.type+'_ID'],method:['='],value: [id]});
		});
        var filter = buildFilter(cfg);

		Ext.Ajax.request({
			url: AppKit.c.path+"/web/api/json",
			params: {
				
				target: cfg.target,
				'columns[0]': cfg.type+'_ID',
				'columns[1]': cfg.type+'_NAME',
				'columns[2]': 'HOST_NAME',
                'columns[3]': 'HOST_ADDRESS',
				'columns[4]': cfg.type+'_CUSTOMVARIABLE_VALUE',
				filters_json : Ext.encode({
					"type": "AND",
					"field": [
						idJSON,filter]
				}),
				target_field: cfg.type+'_ID'
			},
			failure: function(resp) {
				var err = Ext.decode(resp.responseText);
				AppKit.notifyMessage("EventDB Error",_("Couldn't fetch edb information")+",<br/> "+err.msg);
				
			},
			success: function(resp) {
				var data = Ext.decode(resp.responseText);
				if(!data)
					AppKit.notifyMessage("EventDB Error",_("Invalid server response"));
				else
					callback.call(scope,Ext.apply(cfg,{edb:data}),cfg);
			}
		});
	}
	
	/**
	 * Update node css and link to eventDB, if possible
	 * @param {Object} data : The result of the json request
	 * @param {Object} cfg : Meta information provided by the template, @requestEdbElements
	 * 
	 * @author jmosshammer<jannis.mosshammer@netways.de>
	 */
	var drawLinks = function(data,cfg) {
		var objects = Ext.DomQuery.select('.edb_cronk_sel.unfinished');
		var map = {}
		if(!data.edb) {
			data.edb = {
				result: []
			}
		}
		// create a service->filter map for easier access
		Ext.iterate(data.edb.result || [], function(elem) {
	
				map[elem[cfg.type+'_ID']] = Ext.decode(elem[cfg.type+'_CUSTOMVARIABLE_VALUE']); 
				map[elem[cfg.type+'_ID']].host = elem.HOST_NAME;
                map[elem[cfg.type+'_ID']].address = elem.HOST_ADDRESS;
				map[elem[cfg.type+'_ID']].service = elem[cfg.type+'_NAME'];

		});
        AppKit.log(cfg);
		// Add host/service as a filter per default
		Ext.iterate(objects,function(DOMNode) {
			var id = DOMNode.getAttribute('edb_val');
			var elem = Ext.get(DOMNode);
			if(map[id])
				return true;
            else if(!cfg.cv_filter)
    			map[id] = {host: DOMNode.getAttribute('host'), service: DOMNode.getAttribute('service')}
		});
		
		Ext.iterate(objects,function(DOMNode) {
			var id = DOMNode.getAttribute('edb_val');
			var elem = Ext.get(DOMNode);
			if(!map[id]) {
				elem.replaceClass("unfinished","notAvailable");
			} else {
				elem.removeClass("unfinished")
				Ext.get(elem.findParentNode('td')).addClass(["edb_cronk_sel","x-icinga-grid-link","available"]);
				new Ext.ToolTip({
					target: Ext.get(elem.findParentNode('td')),
					html: _('Jump to event-db')
				}).doLayout();
				
				buildLink(elem,map[id]);
			}
			return true;
		})
	}
	/**
	 * Create link with jsonFilter from customvar in a specific cell 
	 * @param {DOMNode} elem	The DOM Node of the cell's div
	 * @param {Object} data		The customvar result from the icinga-api
	 * @author jmosshammer<jannis.mosshammer@netways.de>
	 */
	var buildLink = function(elem,data) {
		
		var cronk = {
			parentid: Ext.id(),
			title: 'EventDB: '+(data.service != "undefined" ? (data.host+" : "+data.service) : data.host),
			crname: 'icingaEventDB',
			closable: true,
			params: {
				action: 'EventDB.Index',
				module: 'Cronks',
				FilterJSON:Ext.encode({
				
					"hostFilter":{
						"include_pattern":data.host,
						"include_pattern_type":"exact",
						"exclude_pattern_type":"disabled",
						"exclude_pattern":false,
						"include_set":[],
						"exclude_set":[]
					},
					"programFilter":{
						"include_pattern":false,
						"include_pattern_type":"disabled",
						"exclude_pattern":false,
						"exclude_pattern_type":"disabled",
						"include_set":[],
						"exclude_set":[]
					},
					"messageFilter":{
						"items": [data.msg] || []
					},
					"misc":{
						"hideAck":false
					},
					"sourceExclusion":data.sourceExclusion || [],
					"priorityExclusion":data.priorityExclusion || [],
					"facilityExclusion":data.facilityExclusion || [],
					"timespan":{
						"from": data.startTime || -1,
						"to":-1
					},
					"display":{
						"order":{
							"field":"modified",
							"dir":"desc"
						},
						"group":{
							"field":null
						},
						"count":"id",
						"limit":25
					}
				})
			}
		}
		
		elem.on("click",Cronk.util.InterGridUtil.gridFilterLink.createDelegate(this,[cronk, {}]));
	}
	
	
	Cronk.grid.ColumnRenderer.edbColumn = function(cfg) {
		return function(value, garbage, record, rowIndex, colIndex, store) {
			edbColumnSelector.delay(500,null,null,[cfg,record]);
			return '<div class="edb_cronk_sel unfinished" edb_val="'+value+'" host="'+record.data.host_name+'" service="'+record.data.service_name+'" style="width:25px;height:24px;display:block"></div>'
		}
	}
		

})()
