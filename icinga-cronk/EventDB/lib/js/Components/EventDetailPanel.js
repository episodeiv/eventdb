Ext.ns("Cronk.EventDB.Components").EventDetailPanel = Ext.extend(Ext.Panel, {
    currentId: null,
    layout: 'border',
    showCopyPaste: false,

    constructor: function (cfg) {
        cfg = cfg || {}
        this.parentCmp = cfg.parentCmp;
        this.showCopyPaste = cfg.showCopyPaste || false;
        this.setupCommentStore(cfg);
        Ext.grid.GridPanel.prototype.constructor.apply(this, arguments);
    },

    initCommentGrid: function (cfg) {
        this.setupCommentStore(cfg);
        Ext.apply(cfg, this.getGridLayout());
    },

    loadCommentsForEventId: function (id) {
        if (typeof id === "undefined" && this.currentId === null) {
            AppKit.log("EventDetailPanel: Reload without eventId requested");
            return;
        } else if (typeof id === "undefined") {
            id = this.currentId;
        }

        this.commentStore.baseParams = {
            event: id,
            offset: 0,
            limit: 25
        };
        this.commentStore.load();
        this.currentId = id;
    },

    setupCommentStore: function (cfg) {
        this.commentStore = new Ext.data.JsonStore({
            autoLoad: false,
            autoDestroy: true,
            baseParams: {
                offset: 0,
                limit: 25,
                count: 'id'
            },
            totalProperty: 'count',
            paramNames: {
                start: 'offset'
            },
            url: cfg.commentUrl,
            root: 'comments',
            fields: [
                {
                    name: 'id'
                },

                {
                    name: 'user'
                },

                {
                    name: 'message'
                },

                {
                    name: 'type'
                },

                {
                    name: 'created'
                }
            ]
        });
    },

    initComponent: function () {
        Ext.Panel.prototype.initComponent.apply(this, arguments);

        this.add(new Ext.grid.GridPanel(this.getGridLayout()));
        this.add(this.getDetailTable());

    },

    update: function () {
        this.loadCommentsForEventId();
    },

    displayComments: function (event) {
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


    getDetailTable: function () {
        var additionalFields = '';
        for (var i = 0; i < this.additionalFields.length; ++i) {
            additionalFields += '<tr><td><b>' + this.additionalFields[i].header +' :</b></td><td>'
                + this.additionalFields.dataIndex + '</td></tr>';
        }
        var cmp = new Ext.Container({
            data: {
                type: '0',
                host: 'None',
                message: 'none',
                facility: 0,
                priority: 0,
                program: 'None',
                created: '0',
                address: '0.0.0.0'
            },
            autoScroll: true,
            layout: 'fit',
            tpl: new Ext.XTemplate(
                "<table style='font-size:11px' width='100%'>",
                "<tr>",
                "<td><b>Host:</b></td>",
                "<td>",
                "<span isHostField='true' hostName='{real_host}' ",
                " style='color:blue;text-decoration:underline;cursor:pointer;'",
                " class='eventdb-host {host_name}'>",
                "<div style='float:left' class='icon-16 icinga-icon-host'></div> {host_name}",
                "</span>",
                "</td>",
                "</tr><tr colspan='2'>",
                "<td><b>Message:</b></td>",
                "</tr><tr colspan='2' rowspan='2'>",
                "<td  colspan='2' >",
                "<div style='margin:auto;border-top:1px solid grey;margin:1em;padding:0.4em;border-bottom: 1px solid grey'>",

                "<div style='word-wrap:break-word;width:100%;'>",
                "{[Cronk.EventDB.Helper.extendedmessageFormatter(values.message)]}",
                "</div>",
                '</div>',
                "</td>",
                "</tr><tr>",
                "<td><b>Priority:</b></td><td>{priority}</td>",
                "</tr><tr>",
                "<td><b>Program:</b></td><td>{program}</td>",
                "</tr><tr>",
                "<td><b>Created:</b></td><td>{created}</td>",
                "</tr><tr>",
                "<td><b>Type</b></td><td>{[Cronk.EventDB.Helper.resolveTypeNr(values.type)]}</td>",
                "</tr><tr>",
                "<td><b>Address:</b></td><td>{address}</td>",
                "</tr><tr>",
                "<td><b>Facility:</b></td><td>{facility}</td>",
                "</tr>",
                additionalFields,
                "</table>"
            )
        });

        this.detailTable = cmp;
        var subGrid = new Cronks.EventDB.Components.SubGridFactory();
        // wrap the update function to catch updates before DOM is rendererd
        that = this;
        this.detailTable.lazyUpdate = function (event) {

            cmp.currentEvent = event;
            if (cmp.rendered) {
                cmp.update(event.data);
                Cronk.EventDB.Helper.initCronkLinks(cmp.el.dom);
                subGrid.grid.getStore().setBaseParam("group_leader", event.data.id);
            } else {
                cmp.on("afterrender", function () {
                        cmp.update(event.data);
                        subGrid.grid.getStore().setBaseParam("group_leader", event.data.id);
                        Cronk.EventDB.Helper.initCronkLinks(cmp.el.dom);
                    },
                    this, {
                        single: true
                    });
            }
            subGrid.grid.getStore().load();

        }


        return new Ext.TabPanel({
            activeTab: 0,
            region: 'east',
            width: "50%",
            tabPosition: 'bottom',
            defaults: {
                padding: 4
            },
            items: [
                {
                    title: _('Details'),
                    layout: 'fit',
                    width: "100%",
                    tbar: {
                        items: [
                            {
                                text: _('Mail me'),
                                iconCls: 'icinga-icon-bell',
                                handler: function (btn) {
                                    btn.disable();
                                    Ext.Ajax.request({
                                        url: this.mailMeUrl,
                                        success: function () {
                                            btn.enable();
                                            AppKit.notifyMessage(_('Mail sent'), _('Mail sent'));
                                        },
                                        failure: function (response) {
                                            btn.enable();
                                        },
                                        method: 'GET',
                                        params: { event: this.currentId }
                                    });
                                },
                                scope: this
                            }
                        ].concat(this.showCopyPaste ? [this.getClipboardButton(cmp)] : [])
                    },
                    items: cmp
                },
                {
                    title: _('Event group'),
                    layout: 'fit',
                    items: subGrid.grid
                }
            ]
        });
    },

    getClipboardButton: function (cmp) {

        var clipboardBtnMessage = "";
        if (typeof window.clipboardData !== "undefined") {
            clipboardBtnMessage = _("Copy message to clipboard");
        } else {
            clipboardBtnMessage = _("Open popup for copying");
        }
        return {
            label: clipboardBtnMessage,
            iconCls: 'icinga-icon-note',
            text: clipboardBtnMessage,
            handler: function () {
                if (!cmp.currentEvent)
                    return;
                Cronk.EventDB.Helper.clipboardHandler(
                    Ext.util.Format.htmlEncode(
                        cmp.currentEvent.get("created") + " - " + cmp.currentEvent.get("host_name") + " - " + cmp.currentEvent.get("priority") + " - " + cmp.currentEvent.get("message")
                    )
                );
            },
            scope: this
        }
    },

    getGridLayout: function () {
        return {
            store: this.commentStore,
            layout: 'fit',
            region: 'center',
            colModel: new Ext.grid.ColumnModel({
                defaults: {
                    width: 80,
                    sortable: true,
                    resizeable: true,
                    fixed: true
                },
                columns: [
                    {
                        header: _('Type'),
                        dataIndex: 'type',
                        renderer: function (v) {
                            switch (v) {
                                case '0':
                                    return '<div class="icon-16 icinga-icon-note" qtip="' + _('Comment') + '"></div>';
                                case '1':
                                    return '<div class="icon-16 icinga-icon-accept" qtip="' + _('Acknowledge') + '"></div>';
                                case '2':
                                    return '<div class="icon-16 icinga-icon-cancel" qtip="' + _('Revoke') + '"></div>';
                            }
                        },
                        width: 40,
                    },
                    {
                        header: _('Author'),
                        dataIndex: 'user'
                    },

                    {
                        header: _('Created'),
                        dataIndex: 'created',
                        width: 150
                    },

                    {
                        header: _('Message'),
                        dataIndex: 'message',
                        xtype: 'templatecolumn',
                        tpl: '<div style="white-space: pre-line;">{message}</div>'
                    }
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
            tbar: {
                items: [
                    {
                        text: _('Add comment'),
                        iconCls: 'icinga-icon-add',
                        handler: function (btn) {
                            btn.ownerCt.ownerCt.ownerCt.ownerCt.ownerCt.items.get(0).items.get(0).showCommentForm([this.currentId]);
                        },
                        scope: this
                    }
                ]
            },
            border: false
        }
    }
});
