Ext.ns("Cronk.EventDB.Components").EventDetailPanel = Ext.extend(Ext.Panel, {
    currentId: null,
    layout: 'border',
    
    
    constructor: function(cfg) {
        cfg = cfg || {}
        this.setupCommentStore(cfg);
        Ext.grid.GridPanel.prototype.constructor.apply(this,arguments);
    },

    initCommentGrid: function(cfg) {
        this.setupCommentStore(cfg);
        Ext.apply(cfg,this.getGridLayout());
    },

    loadCommentsForEventId: function(id) {
        if(typeof id === "undefined" && this.currentId === null) {
            AppKit.log("EventDetailPanel: Reload without eventId requested");
            return;
        } else if(typeof id === "undefined") {
            id = this.currentId;
        }

        this.commentStore.baseParams = {event:id, offset: 0, limit: 25};
        this.commentStore.load();
        this.currentId = id;
    },

    setupCommentStore: function(cfg) {
        this.commentStore = new Ext.data.JsonStore({
            autoLoad: false,
            autoDestroy: true,
            baseParams: {
                offset:0,
                limit:25,
                count: 'id'
            },
            totalProperty: 'count',
            paramNames: {
                start: 'offset'
            },
            url: cfg.commentUrl,
            root: 'comments',
            fields: [
                {name: 'id'},
                {name: 'user'},
                {name: 'message'},
                {name: 'type'},
                {name: 'created'}
            ]
        });
    },

    initComponent: function() {
        Ext.Panel.prototype.initComponent.apply(this,arguments);

        this.add(new Ext.grid.GridPanel(this.getGridLayout()));
        this.add(this.getDetailTable());
    },

    update: function() {
        this.loadCommentsForEventId();
    },

    displayComments: function(event) {
        this.loadCommentsForEventId(event.get('id'));
        this.detailTable.lazyUpdate(event);
        this.ownerCt.expand();
        this.ownerCt.show();
    },
/*
 * 			{name: 'id'},
			{name: 'host_name'},
			{name: 'address'},
			{name: 'facility'},
			{name: 'priority'},
			{name: 'program'},
			{name: 'created'},
			{name: 'modified'},
			{name: 'message'},
			{name: 'ack'},
			{name: 'type'},
			{name: 'real_host'}
 *
 */
    getDetailTable: function() {

        var cmp = new Ext.Container({
            data: {type: '0',host:'None',message:'none',facility:0,priority:0,program:'None',created: '0',address:'0.0.0.0'},
            autoScroll:true,
            layout: 'fit',
            tpl: new Ext.XTemplate(
                "<table style='font-size:12px'>",
                    "<tr>",
                       "<td><b>Type</b></td><td>{[Cronk.EventDB.Helper.resolveTypeNr(values.type)]}</td>",
                     "</tr><tr>",
                        "<td><b>Host:</b></td><td>{host_name}</td>",
                     "</tr><tr colspan='2'>",
                       "<td><b>Message:</b></td>",
                     "</tr><tr colspan='2' rowspan='2'>",
                       "<td>",
                           "<div style='word-wrap:break-word;width:100%;height:40px;overflow:auto'>",
                               "{message}",
                           "</div>",
                       "</td>",
                    "</tr><tr>",
                       "<td><b>Address:</b></td><td>{address}</td>",
                    "</tr><tr>",
                       "<td><b>Facility:</b></td><td>{facility}</td>",
                    "</tr><tr>",
                       "<td><b>Priority:</b></td><td>{priority}</td>",
                    "</tr><tr>",
                       "<td><b>Program:</b></td><td>{program}</td>",
                    "</tr><tr>",
                       "<td><b>Created:</b></td><td>{created}</td>",
                    "</tr>",
                "</table>"
            )
        });

        this.detailTable = cmp;
        // wrap the update function to catch updates before DOM is rendererd
        this.detailTable.lazyUpdate = function(event) {
            if(cmp.rendered) {    
                cmp.update(event.data);
            } else {
                cmp.on("afterrender",function() {
                    cmp.update(event.data);
                },this,{single:true});
            }
            
        }
        return new Ext.Panel({
            title: _('Details'),
            layout: 'fit',
            region:'east',
            width:"20%",
            items: cmp
        });
    },

    getGridLayout: function() {
        return {
            store: this.commentStore,
            layout: 'fit',
            region: 'center',
            colModel: new Ext.grid.ColumnModel({
                defaults: {
                    width: 80,
                    sortable: true
                },
                columns: [{
                    header: _('Type'), dataIndex: 'type',renderer: function(v) {
                        switch(v) {
                            case '0':
                                return '<div class="icon-16 icinga-icon-note" qwidth="200px" qtip="'+_('Comment')+'"></div>';
                            case '1':
                                return '<div class="icon-16 icinga-icon-accept" qwidth="200px" qtip="'+_('Acknowledge')+'"></div>';
                            case '2':
                                return '<div class="icon-16 icinga-icon-cancel" qwidth="200px" qtip="'+_('Revoke')+'"></div>';
                        }
                    }},
                    {header: _('Author'), dataIndex: 'user'},
                    {header: _('Created'), dataIndex: 'created', width: 150},
                    {header: _('Message'), dataIndex: 'message', width: 200}
                ]
            }),
            bbar: new Ext.PagingToolbar({
                pageSize: 25,
                store: this.commentStore,
                displayInfo: true,
                displayMsg: _('Displaying comments {0} - {1} of {2}'),
                emptyMsg: _('No comments to display')
            }),
            frame: true,
            border: false
        }
    }
});
	