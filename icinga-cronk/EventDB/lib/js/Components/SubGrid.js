
(function() {
    "use strict";

    Ext.ns("Cronks.EventDB.Components").SubGridFactory = function(id) {
        this.grid = new Ext.grid.GridPanel({
            store: new Ext.data.JsonStore({
                autoLoad:false,
                autoDestroy:true,
                remoteSort: true,
                paramNames: {
                    start: 'offset'
                },
                url: Cronk.EventDB.BaseURL,
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
            }),
            columns: [{
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
                dataIndex: 'host_name',
                header: _('Host'),
                sortable: true,
                width: 100,
                xtype:'templatecolumn',
                tpl: new Ext.XTemplate(
                    '<span isHostField="true"',
                    ' hostName="{real_host}" ',
                    ' style="color:blue;text-decoration:underline;cursor:pointer" ',
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
                width: 100,
                renderer: function(v) {
                    return '<div class="tag '+v.toLowerCase()+'">'+v+'</div>';
                }
            },{
                dataIndex: 'message',
                header: _('Message'),
                sortable: true,
                width: 200,
                xtype:'templatecolumn',
                css: 'cursor: pointer',
                tpl: new Ext.XTemplate(
                    '<div ext:qtip="{[fm.htmlEncode(values.message)]}">',
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
                width: 200
            }]
        });
    }
})()
