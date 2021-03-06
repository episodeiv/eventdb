Ext.ns("Cronk.EventDB");


Cronk.EventDB.MainView = function(cfg) {

    var CE = cfg.CE;
    this.id = CE.id;
    var showCopyPaste = cfg.showCopyPaste;
    var parentCmp = cfg.parentCmp;
    var url = cfg.eventUrl;
    Cronk.EventDB.BaseURL = url;
    var eventDetailPanel = new Cronk.EventDB.Components.EventDetailPanel(cfg);
    var commentForm = new Cronk.EventDB.Components.CommentForm(cfg, eventDetailPanel);

    var ackFilterBtn = new Ext.Button({
        text: _('Ack'),
        iconCls: 'icinga-icon-accept',
        enableToggle: true,
        listeners :  {
            toggle: function(e,state) {
                e.setIconClass('icinga-icon-'+(state ? 'cancel' : 'accept')); 
                fm.toggleAcknowledged(!state);
                eventGrid.refreshTask.delay(1500);		
            }	
        }
    });	
	
    var quickFilterBar = new Ext.ButtonGroup({
        xtype: 'buttongroup',
        text: _('Priority'),
        defaults: {
            xtype: 'button',
            bubbleEvents: ['toggle'],
            enableToggle: true
        },
        events: ['toggle'],
        listeners: {
            toggle: function(e) {

                var elem = e.findParentByType('buttongroup');
                var btns = elem.findByType('button');
                fm.togglePriority(e.value.toString(), e.pressed)
				
                eventGrid.refreshTask.delay(1500,null,eventGrid);
            },
            scope:this
        },

        syncWithFilter: function(f) {
            
            var filter = f || fm.getFilterObject();
            var filterBtn
            if(parentCmp.el)
                filterBtn = Ext.DomQuery.selectNode('.filterBtn',parentCmp.el.dom);
            if(fm.hasActiveFilter() && filterBtn) {
                Ext.get(filterBtn).addClass('activeFilter');
            } else if(filterBtn) {
                Ext.get(filterBtn).removeClass('activeFilter');
                
            }
            if(!fm.showsAcknowledged()) {
                ackFilterBtn.toggle(true,true);
                ackFilterBtn.setIconClass('icinga-icon-cancel');
            } else {
                ackFilterBtn.toggle(false,true);
                ackFilterBtn.setIconClass('icinga-icon-accept');	
            }
            filter = filter.priorityExclusion;		
            if(!filter) {
                return true;
            }
            Ext.iterate(this.items.items,function(i) {
                if(filter.indexOf(i.value.toString()) > -1 || filter.indexOf(i.value) > -1) {
                    i.toggle(false,true);	
                } else {	
                    i.toggle(true,true);	
                }
            });
            return true;
        },
        items: [
        {
            text: 'E',
            ctCls: 'tag emergency',	
            pressed: true,
            tooltip: _('Show emergency'),	
            value: 0
        },{
            text: 'A',
            ctCls: 'tag alert',
            pressed: true,
            tooltip: _('Show alert'),	
            value: 1	
        },{
            text: 'C',
            ctCls: 'tag critical',
            tooltip: _('Show critical'),
            pressed: true,	
            value: 2
        },{
            text: 'Er',
            ctCls: 'tag error',
            tooltip: _('Show error'),
            pressed: true,	
            value: 3
        },{
            text: 'W',
            ctCls: 'tag warning',
            tooltip: _('Show warning'),
            pressed: true,	
            value: 4
        },{
            text: 'N',
            ctCls: 'tag notice',
            tooltip: _('Show notice'),
            pressed: true,	
            value: 5
        },{
            text: 'I',
            tooltip: _('Show info'),
            ctCls: 'tag info',	
            pressed: true,	
            value: 6
        },{
            text: 'D',
            tooltip: _('Show debug'),
            ctCls: 'tag debug',	
            pressed: true,	
            value: 7
        }]
    });


    var eventStore = new Ext.data.JsonStore({
        autoLoad:false,
        autoDestroy:true,
        baseParams: {
            offset:0,
            count: 'id',	
            limit:50
        },
        remoteSort: true,
        paramNames: {
            start: 'offset'
        },
        url: url,
        root: 'events',
        totalProperty: 'count',
        fields: [
        {
            name: 'id'
        },{
            name: 'host_name'
        },{
            name: 'address'
        },{
            name: 'facility'
        },{
            name: 'priority'
        },{
            name: 'program'
        },{
            name: 'created'
        },{
            name: 'modified'
        },{
            name: 'message'
        },{
            name: 'ack'
        },{
            name: 'type'
        },{
            name: 'real_host'
        }]
    });


    var fm = new Cronk.EventDB.FilterManager({
        url: url, 
        parentCmp: parentCmp
    });
	
    fm.addListener('applyFilter', function(filters) {		
			
        eventGrid.fireEvent('statechange');
        eventGrid.setPageSize(filters.display.limit);
        eventStore.baseParams = {
            "jsonFilter": Ext.encode(filters)
            };
        
        Cronk.Registry.get(CE.id).params.FilterJSON = Ext.encode(fm.getFilterObject());
        quickFilterBar.syncWithFilter();
        eventGrid.refresh();
    },this,{
        buffer:true
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
                var el = Ext.get(t);
                var index = this.grid.getView().findRowIndex(t);
                var record = this.grid.store.getAt(index);
                if(!this.grid.selectedRecords) {
                    this.grid.selectedRecords =[];
                }
                if(el.hasClass('x-grid3-check-col-autoclear')) {
                    el.replaceClass('x-grid3-check-col-autoclear','x-grid3-check-col');
                    el.parent().addClass('x-grid3-check-col-autoclear-bg');
                    return;
                }
                if(!el.hasClass('x-grid3-check-col-on')) {
                    this.grid.selectedRecords.push(record.id);
                    el.replaceClass('x-grid3-check-col','x-grid3-check-col-on'); 
					
                } else {
                    this.grid.selectedRecords.remove(record.id);
                    el.replaceClass('x-grid3-check-col-on','x-grid3-check-col'); 
                }
                this.grid.updateCommentButton();
            }
        },
	
        renderer: function(v, p, record) {
            if (this.initialValues[record.id]) {
                this.grid.updateCommentButton();
            } else {
                this.initialValues[record.id] = v;
            }

            var autoclear = false;
            var statepostfix = '';
            if (this.grid.selectedRecords && this.grid.selectedRecords.indexOf(record.id.toString()) !== -1) {
                statepostfix = '-on';
            }

            if (record.json.group_autoclear && record.json.group_autoclear > 0) {
                autoclear = true;
                if(!statepostfix) statepostfix = '-autoclear';
                else p.css += ' x-grid3-check-col-autoclear-bg';
            }

            return String.format(
                '<div record="'+record.id+'" class="x-grid3-check-col{0} {1}" {2}>&#160;</div>',
                statepostfix,
                this.createId(),
                (autoclear ? 'ext:qtip="This event should only be acknowledged with a matching clear event"' : '')
            );
        },
	
        createId: function(){
            return 'x-grid3-cc-' + this.id;
        }
    };
	
    var ack = new checkColumn({
        header: _(''),
        dataIndex: 'ack',
        width: 25,
        fixed: true,
        menuDisabled: true
    });
	
	

    var _eventGrid = Ext.extend(Ext.grid.GridPanel, {
        setPageSize: function(size) {
            this.bottomToolbar.pageSize = size;
        },
        rendered: false,
        
        setPagingBar: function() {
            this.bbar = new Cronk.EventDB.Components.OptimisticPagingToolbar({
                limit: 50,
                id: 'pager_'+this.id,
                store: eventStore,
                afterPageText: '',
                displayInfo: true,
                displayMsg: _('Displaying events {0} - {1}'),
                emptyMsg: _('No events to display')
            });
        },

        constructor: function(cfg) {
            Ext.apply(this,cfg);
            this.setPagingBar();
            this.addEvents({
                'statechange': true,
                'hostFilterChanged': true
            });
            Ext.grid.GridPanel.prototype.constructor.call(this);
            this.store.on("beforeload",function() {
                
                var f = fm.getFilterObject();
                var isEmpty = true;
                for(var i in f) {
                    isEmpty = false;
                    break;
                }
                if(isEmpty) {
                    f = fm.getFilterObject(true);
                }
                var sortState = this.getSortState();
                
                if(Ext.isObject(sortState)) {
                    f.display.order =
                    {
                        dir: sortState.direction.toLowerCase(),
                        field: sortState.field
                    }
                }
                if(!this.ignoreBaseFilter) {
                    quickFilterBar.syncWithFilter();
                }

                this.setBaseParam('jsonFilter',Ext.encode(f));

            },this.store);
            this.store.on("load", function() {
                this.buildInterGridLink();
                Cronk.EventDB.Helper.initCronkLinks(parentCmp.el.dom);
                this.updateSelected();
            },this)
            this.reenableTextSelection();
        },

        buildInterGridLink: function() {
            var elems = Ext.DomQuery.select('span[isHostField=true]',parentCmp.el.dom);
            Ext.iterate(elems,function(elem) {
                elem.removeAttribute("isHostField");

                Ext.get(elem).on("click",function(ev,e) {
                    var host_name = e.getAttribute('hostName');
                    if(!host_name || host_name == "false")
                        return true;
                    var cronk = {
                        parentid: Ext.id(),
                        title: 'Services for '+host_name,
                        crname: 'gridProc',
                        closable: true,
                        params: {
                            template: 'icinga-service-template'
                        }
                    };
                    var filter = {};
				
                    filter["f[host_name-value]"] = host_name; 	
                    filter["f[host_name-operator]"] = 50;
                    Cronk.util.InterGridUtil.gridFilterLink(cronk, filter);
                    return true;
                });
            });

   			

        },
		
        unselectAll: function(viewOnly) {
            if(!viewOnly) {
                this.selectedRecords = [];
            }
            var elem = Ext.DomQuery.select('.x-grid3-check-col-on',parentCmp.el.dom);
            Ext.iterate(elem,function(i) {
                Ext.get(i).replaceClass('x-grid3-check-col-on','x-grid3-check-col',parentCmp.el.dom);
            },this);
            this.updateCommentButton();
        },
        selectAll: function() {
            var elem = Ext.DomQuery.select('.x-grid3-check-col',parentCmp.el.dom);
            Ext.iterate(elem,function(i) {	
                Ext.get(i).replaceClass('x-grid3-check-col','x-grid3-check-col-on',parentCmp.el.dom);	
                var id = Ext.get(i).getAttribute("record");
                this.selectedRecords.push(id);
            },this);
			
            this.updateCommentButton();
        },
        selectPage: function() {
            
        },
        updateCommentButton: function() {
            if(this.selectedRecords.length) {
                this.commentButton.menu.items.get(0).enable();
                this.commentButton.menu.items.get(0).setText(this.selectedRecords.length+_(" selected items"));	  		
            } else {
                this.commentButton.menu.items.get(0).setText(_('Current selection (nothing selected)'));
                this.commentButton.menu.items.get(0).disable();
            }
        },
        updateSelected: function() {
            this.unselectAll(true);
            Ext.iterate(this.selectedRecords,function(r) {
                var elem = Ext.DomQuery.select('div.x-grid3-check-col[record='+r+']',parentCmp.el.dom);
                Ext.iterate(elem, function(i) {	
                    Ext.get(i).replaceClass('x-grid3-check-col','x-grid3-check-col-on',parentCmp.el.dom);	
                },this)
            },this);
            this.updateCommentButton();	
		
        },
        /**
		* http://extjs.com/forum/showthread.php?t=22218
		* For non-IE browsers, this is fixed with a CSS addition.
		*/
        reenableTextSelection : function(){
            var grid = this;

            if(Ext.isIE){
                grid.store.on("load", function(){
                    var elems=Ext.DomQuery.select("div[unselectable=on]", parentCmp.el.dom);
                    for(var i=0, len=elems.length; i<len; i++){
                        elems[i].unselectable = "off";
                    }

                });
            }
        },
		
        // buffer store reload
        refreshTask : new Ext.util.DelayedTask(function() {
            if(this.store)
                this.store.load();
            else 
                eventStore.load();
            quickFilterBar.active = true;
			
        }),
        refresh: function() {
            this.refreshTask.delay(1000,null,this);
        },
        resolveType: function(v) {	
            return Cronk.EventDB.Helper.resolveTypeNr(v);
        },
		
        getState: function() {
		
            var state = {
                height: this.getHeight(),
                width: this.getWidth(),
                //storeParams: this.store.baseParams,
                filters: fm.getFilterObject()
            };
            return state;
        },
        applyState: function(state) {	
			
            if(state.colModel)
                this.getColumnModel().setConfig(Ext.decode(state.colModel))
            this.setHeight(state.height);
            this.setWidth(state.width);
            //this.store.baseParams = state.storeParams;
			
            if(state.filters) {
                fm.setFilterObject(state.filters);
            }
		
            this.setPageSize(fm.getDisplayLimit());
            quickFilterBar.syncWithFilter();

            Cronk.Registry.get(CE.id).params.FilterJSON = Ext.encode(fm.getFilterObject());
        },
        viewConfig: {
			
            getRowClass: function(record,index) {
                return 'tag '+record.get('priority').toLowerCase();	
            }
        }
    });
    Ext.ns("Cronk.EventDB").EventGrid = _eventGrid;
    var eventGrid = new _eventGrid({
        id: "evGrid_"+this.id,
		
        columns: [{
            showHeader:false,
            width:22,
            fixed:true,
            menuDisabled: true,
            dataIndex: 'type',
            xtype: 'templatecolumn',
            tpl: new Ext.XTemplate(
                '<div class="eventdb-type {[Cronk.EventDB.Helper.resolveTypeNr(values.type).toLowerCase()]}">',
                '<div ext:qwidth="500" ext:qtip="<b> ({type}) {[Cronk.EventDB.Helper.resolveTypeNr(values.type)]} - {priority} : </b><br/> {[Cronk.EventDB.Helper.extendedmessageFormatter(values.message)]}" class="icon-16">',
                '</div>',
                '</div>',{
                    eventGrid: eventGrid
                }
            )
        },ack,{
            dataIndex: 'id',
            id: 'id',
            hidden:true,
            header: _('ID'),
            sortable: true,
            width: 75
        },{
            dataIndex: 'ack',	
            header: _('Ack'),
            menuDisabled: true,
            renderer: function(v,meta,record,rowIdx,colIdx) {
                return '<div class="icon-16 icinga-icon-'+(v == 1 ? 'accept' : 'none' )+'"></div>';	
            },
            width:25
        },{
            dataIndex: 'type',
            header: _('Source'),
            sortable: true,
            hidden: true,
            width: 70,
            renderer: function(v) {
                var typename = eventGrid.resolveType(v);
                return '<span class="eventdb-type '+typename.toLowerCase()+'">'+ 
                '</div>'+typename+'</span>';
            }
        },{
            dataIndex: 'host_name',
            header: _('Host'),
            sortable: true,
            width: 130,
            xtype:'templatecolumn',
            tpl: new Ext.XTemplate(
                '<span isHostField="true"',
                ' hostName="{real_host}" ',
                ' style="color:blue;text-decoration:underline;cursor:pointer;" ',
                'class="eventdb-host {host_name}">',
                '<div style="float:left" class="icon-16 icinga-icon-host"></div>',
                '{host_name}',
                '</span>'
                )
        },{
            dataIndex: 'address',
            header: _('Address'),
            hidden: true,
            width: 100
        },{
            dataIndex: 'priority',
            header: _('Priority'),
            sortable: true,
            width: 105,
            renderer: function(v) {
                return '<div class="tag '+v.toLowerCase()+'">'+v+'</div>';
            }
        },{
            dataIndex: 'message',
            xtype: 'actioncolumn',
            width: 35,
            hidden: !showCopyPaste,
            items: [{
                getClass: function(v,meta,rec) {
                    var useNativeCopy = false;
                    if(typeof window.clipboardData !== "undefined") {
                        useNativeCopy = true;
                    }
                        
                    this.items[0].tooltip = useNativeCopy ?
                    _("Copy message to clipboard") :
                    _("Open popup for copying");

                    return 'icon-16 icinga-icon-note';
                },
                handler: function(cmp,rowidx) {
                    var record = eventStore.getAt(rowidx);
                    var created = record.get("created");
                    var host = record.get("host_name");
                    var message = record.get("message");
                    var priority = record.get("priority");
                    Cronk.EventDB.Helper.clipboardHandler(created+" - "+host+" - "+priority+" - "+message);
                }
            }]

        },{
            dataIndex: 'message',
            header: _('Message'),
            sortable: true,
            width: 360,
            xtype:'templatecolumn',
            tpl: new Ext.XTemplate(
                '<div ext:qwidth="500" ext:qtip="{[Cronk.EventDB.Helper.extendedmessageFormatter(fm.htmlEncode(values.message))]}">',
                '{[Cronk.EventDB.Helper.messageFormatter(values.message)]}',
                '</div>'
            )
               
        },{
            dataIndex: 'program',
            header: _('Program'),
            sortable: true,
            renderer: function(v) {
                return '<span class="eventdb-program '+v.toLowerCase()+'">'+ 
                '<div style="float:left" class="icon-16"></div>'+v+'</span>';
            },
            width: 100
        },{
            dataIndex: 'facility',
            header: _('Facility'),
            sortable: true,
            renderer: function(v) {
                if(!v)
                    return "INVALID FACILITY!";
                return '<span class="eventdb-facility '+v.toLowerCase()+'">'+ 
                '<div style="float:left" class="icon-16"></div>'+v+'</span>';
            },
            width: 100
        },{
            dataIndex: 'created',
            header: _('Created'),
            sortable: true,
            width: 125
        }
        ],
        store: eventStore,
        stateId: 'db-eventGrid-' + this.id,
        stateful: true,
        stateEvents: ['statechange','sortchange','columnresize','columnmove'],
        tbar: [{
            iconCls: 'icinga-icon-arrow-refresh',
            text: _('Refresh'),
            tooltip: _('Refresh the data in the grid'),
            handler: function(oBtn, e) {
                eventGrid.refresh();
            },
            scope: this
        },{
            iconCls: 'icinga-icon-cog',
            text: _('Settings'),
            menu: {
                items: [{
                    text: _('Auto refresh'),
                    checked: false,
                    id: 'refreshBtn_'+this.id,
                    checkHandler: function(checkItem, checked) {
                        if (checked == true) {
                            if(this.trefresh)
                                this.trefresh.stop();
                            this.trefresh = AppKit.getTr().start({
                                run: function() { 
                                    if(!eventGrid.getStore())
                                        return false;
                                    if(eventGrid.getStore().proxy)
                                        if(eventGrid.getStore().proxy.getConnection())
                                            if(eventGrid.getStore().proxy.getConnection().isLoading())
                                                return true;
                                    eventGrid.getStore().load();
                                    return true;
                                },
                                interval:  20000,
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
            text: _('Select all'),
            handler: function(btn) {
                eventGrid.selectAll();
            },
            scope: this

        },{
            text: _('Clear selection '),
            handler: function(btn) {
                eventGrid.unselectAll();
            },
            scope: this
        },'-',{
            text: _('Filter'),
            cls: 'filterBtn',
            iconCls: 'icinga-icon-pencil',
            listeners: {
                render: function(e) {
                    if(fm.hasActiveFilter())
                        e.addClass('activeFilter');
                    else
                        e.removeClass('activeFilter');
                }
            },
            menu: {
                items: [{
                    text: _('Edit '),
                    iconCls: 'icinga-icon-application-form',
                    handler: function() {
                        eventGrid.store.ignoreBaseFilter = false;
                        fm.show();
                    },
                    scope: this
                },{
                    text: _('Remove'),
                    iconCls: 'icinga-icon-cancel',
                    handler: function() {
                        fm.show(true);
                        fm.clearFilterFields();
                        eventStore.baseParams = {
                            offset:0, 
                            limit:50
                        };
                        eventGrid.fireEvent("statechange");

                        eventGrid.refresh();
                    },
                    scope: this
                }]
            }
        },'-',ackFilterBtn,'-',quickFilterBar,'-',new Ext.form.TextField({
            xtype: 'textfield',
            emptyText: _('Host name'),
            enableKeyEvents: true,
            value: (CE.params || {}).hostQuickFilter, 
            listeners:{ 
                blur: function(el) {
                    var value = el.getValue();
                    eventStore.setBaseParam('hostQuickFilter',value || null);
                    eventGrid.refresh();				
                },
                keydown: function(el) {
                    var value = el.getValue();
                    eventStore.setBaseParam('hostQuickFilter',value || null);
                    eventGrid.refresh();
                },
                scope: this
            }
		
        }),'-',{
            text:'Acknowledge/Comment...',
            iconCls: 'icinga-icon-add',
            ref: '../commentButton',
            menu: [{
                text: _('Current selection'),
                iconCls: 'icinga-icon-application-form',
                handler: function() {
                    commentForm.show(eventGrid);
                },
                
                disabled: true
            },{
                text: _('All results'),
                iconCls: 'icinga-icon-application-cascade',
                handler: function(btn) {
                    commentForm.show(eventGrid,true);
                },
                scope: this
            }]
        },{
            text: 'Copy selected',
            iconCls: 'icinga-icon-note',
            hidden: !showCopyPaste,
            handler: function(cmp) {
                var cmpGrid = cmp.ownerCt.ownerCt;
                if(!cmpGrid.selectedRecords || cmpGrid.selectedRecords.length <1) {
                    AppKit.notifyMessage(_("No event selected"),_("You have to select an event"));
                    return;
                }
                var messages = "";
                for(var i=0;i<cmpGrid.selectedRecords.length;i++) {
                    var record = cmpGrid.store.getById(cmpGrid.selectedRecords[i]);
                    var created = record.get("created");
                    var host = record.get("host_name");
                    var message = record.get("message");
                    var priority = record.get("priority");
                    messages += created + " - " + host + " - " + priority + " - " + message + "\n\n";
                }
                if(messages) Cronk.EventDB.Helper.clipboardHandler(messages);
            }
        }],
        sm: false,		
        plugins: ack,
		
        autoScroll: true,
        listeners: {
            defaults: {
                scope: this
            },
            rowclick: function(grid, rowIndex, e) {
                eventDetailPanel.displayComments(grid.getStore().getAt(rowIndex));
                this.buildInterGridLink();
            },
            keydown: function(ev) {
                if(ev.keyCode == 32) {
                    var toDelete = [];
                    var toAdd = [];
                    Ext.iterate(Ext.DomQuery.select('.x-grid3-row-selected',this.el.dom),function(sRow) {			
                        ev.stopEvent();
                        var el = Ext.get(Ext.DomQuery.select('.x-grid3-check-col, .x-grid3-check-col-on, .x-grid3-check-col-autoclear',sRow)[0]);
                        if(!el) return;
                        if(el.hasClass('x-grid3-check-col-autoclear')) {
                            el.replaceClass('x-grid3-check-col-autoclear','x-grid3-check-col');
                            el.parent().addClass('x-grid3-check-col-autoclear-bg');
                        }
                        var index = this.getView().findRowIndex(sRow);
                        var record =this.store.getAt(index);
                        if(!this.selectedRecords) 
                            this.selectedRecords =[];
                        if(!el.hasClass('x-grid3-check-col-on')) {
                            this.selectedRecords.push(record.id);
                            el.replaceClass('x-grid3-check-col','x-grid3-check-col-on'); 
                            toAdd.push([el,record.id]);
                        } else {
                            toDelete.push([el,record.id]);
                        }

                    },this);
                    if(!toAdd.length) {
                        Ext.iterate(toDelete,function(e) {
                            this.selectedRecords.remove(e[1]);
                            e[0].replaceClass('x-grid3-check-col-on','x-grid3-check-col'); 
                        },this);
                    }

                    this.updateCommentButton();
                    ev.preventDefault();
                    return false;
                } else {
                    return false;
                }
            },	
            beforerender: function(_this) {
                _this.fireEvent('hostFilterChanged', _this, true);
            },
            show: function(_this) {
                _this.store.load();
                _this.updateSelected();	
                _this.renderer = true;
            },
            hostFilterChanged: function(_this, fromrender) {	
		
                fromrender = fromrender || false;
                this.unselectAll();
                if (parentCmp.hostFilter) {					
                    if (!fromrender) {
                        eventGrid.store.load();
                    }
                    _this.fireEvent('statechanged');
                }
            }
        },
        border: false
    });
	
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
            region: 'south',
            title: 'Comments & Details',
            height: 240,
            collapsible: true,
            split: true,
            collapsed: true,
            hidden: true,
            layout: 'fit',
            border: false,
            items: eventDetailPanel
        }]
    });
	
    CE.add(IcingaEventDBCronk);
    CE.doLayout()
	
	
    if(CE.params.hostQuickFilter) {
        eventStore.baseParams = Ext.apply(eventStore.baseParams || {},{
            hostQuickFilter: CE.params.hostQuickFilter
            });
    }
    if(CE.params.FilterJSON) {	
        var params = Ext.decode(CE.params.FilterJSON);
        fm.overwriteDefaults(params);
    }



    if((AppKit.getPrefVal('org.icinga.autoRefresh') && AppKit.getPrefVal('org.icinga.autoRefresh') != 'false'))
        Ext.getCmp('refreshBtn_'+this.id).setChecked(true);
    eventGrid.refreshTask.delay(1000);  
};

// must be available from xtemplate
Ext.ns("Cronk.EventDB.Helper").resolveTypeNr = function(v) {
    switch(v) {
        case '0':
        case 0:
            return 'Syslog';
        case '1':
        case 1:
            return 'SNMP';
        case '2':
        case 2:
            return 'Mail';
        default:
            return 'Unknown';
    }
};
// detects URLs in messages

Ext.ns("Cronk.EventDB.Helper").messageFormatter = function(v) {
    v = Ext.util.Format.htmlEncode(v);
    var reg = /((?:http(?:s)?|www\.|[\w\.]+\.(?:de|com|net|org|fr|it|co.uk|ru|ro)\/)[^ ]*?)(?: .*)?[$;| ]/i;

    var matches = v.match(reg);
    if(!Ext.isArray(matches))
        return v;
    for(var i=1;i<matches.length;i++) {
        var replace = matches[i];
        if(!matches[i].match(/^http/))
            replace = "http://"+matches[i];
        v = v.replace(
            matches[i],
            "<a href='"+replace+"' target='_blank' ext:qtip='"+matches[i]+"'>"+Ext.util.Format.ellipsis(matches[i],40)+"</a>"
            );
    }

    return v;
};

Ext.ns("Cronk.EventDB.Helper").extendedmessageFormatter = function(v) {
    var reg = /((?:http(?:s)?|www\.|[\w\.]+\.(?:de|com|net|org|fr|it|co.uk|ru|ro)\/)[^ ]*?)(?: .*)?[$;| ]/i;
    var mibvalreg = /((([\t]{1}[\w\.][^:]*[:]{2})(\w*)?(\.\d+)*):(?:(?:[^\t]*))\t)/gi;
    var mibreg = /(([\t]{1}[\w\.][^:]*[:]{2})(\w*)?(\.\d+)*)/gi;
    var valreg = /(:(?:(?:[^\t]*))\t)/gi;

    v = Ext.util.Format.htmlEncode(v);
    v = v.replace(/\n/gi, "<br />");

    var mibvalmatches = v.match(mibvalreg);

    if(Ext.isArray(mibvalmatches)) {
        for(var i=0;i<mibvalmatches.length;i++) {	
		var mibmatch = mibvalmatches[i].match(mibreg);
		var valmatch = mibvalmatches[i].replace(mibmatch+":","");
		var mibbold = "<b>"+mibmatch+"</b>";
		var valitalic = "<i>"+valmatch+"</i>";
		var formattedmibval = mibbold+":"+valitalic;
		formattedmibval.replace("\t","");
	        v = v.replace(mibvalmatches[i], formattedmibval);
	};
    };

    var matches = v.match(reg);

    if(Ext.isArray(matches)) {
        for(var i=1;i<matches.length;i++) {
                var replace = matches[i];
                if(!matches[i].match(/^http/))
                    replace = "http://"+matches[i];
                v = v.replace(
                    matches[i],
                    "<a href='"+replace+"' target='_blank' ext:qtip='"+matches[i]+"'>"+Ext.util.Format.ellipsis(matches[i],40)+"</a>"
                    );
        }
    }


    return v;
};


Ext.ns("Cronk.EventDB.Helper").initCronkLinks = function(startDOM) {
    var elems = Ext.DomQuery.select('a[cronk_href]',startDOM);
    Ext.iterate(elems,function(elem) {
        Ext.get(elem).on("click",function(ev,e) {
            if(e.getAttribute('node_processed'))
                return true;
            var url = e.getAttribute('cronk_href');
            if(!url)
                return true;
            Cronk.util.InterGridUtil.openExternalCronk(url,url);
            e.setAttribute("node_processed","true")
            return true;
        });
    });
};

Ext.ns("Cronk.EventDB.Helper").clipboardHandler = function(text) {
    if(typeof window.clipboardData !== "undefined") {
        window.clipboardData.clearData();
        window.clipboardData.setData("Text",text);
        AppKit.notifyMessage("EventDB",_("Message copied to clipboard"));
    }
    else {
        new Ext.Window({
            width:'80%',
            height:350,
            title: _('Copy this to your clipboard'),
            padding:5,
            closeAction: 'close',
            autoDestroy:true,
            layout: 'fit',
            items: [{
                xtype: 'textarea',
                value: text
            }]
        }).show(Ext.getBody());
    }
};
