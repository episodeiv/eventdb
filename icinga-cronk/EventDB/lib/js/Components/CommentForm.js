Ext.ns("Cronk.EventDB.Components").CommentForm = function(cfg,detailPanel) {
    var oWin = null;
    var commentAddUrl = cfg.commentAddUrl;
    var userName = cfg.userName;
    var parentCmp = cfg.parentCmp;
    
    return {
        show : function(eventGrid,forall) {
            if(!oWin){ // only create the window once
                oWin = new Ext.Window({
                    title: _('Acknwoledge/Add comment'),
                    layout: 'fit',
                    region: 'center',
                    width: 500,
                    height: 320,
                    closeAction: 'hide',
                    plain: false,
                    modal: true,
                    items: new Ext.FormPanel({
                        labelAlign: 'top',
                        layout: 'form',
                        frame: true,
                        url: commentAddUrl,
                        items: [{
                            xtype: 'textfield',
                            fieldLabel: _('Author'),
                            readOnly: true,
                            name: 'author',
                            allowBlank: false,
                            width: 460,
                            height: 20,
                            value: userName
                        },{
                            xtype: 'radiogroup',
                            showText:true,
                            defaults: {
                                xtype: 'radio'
                            },
                            layout: 'form',
                            labelWidth: 120,
                            items: [{
                                boxLabel: '<span  style="padding-left:18px;height:18px;" qtip="'+_('Comment')+'" class="icon-16 icinga-icon-note">'+_('Comment only')+'</span>',
                                name: 'type',
                                checked: true,
                                inputValue: 'type_0'
                            },{
                                boxLabel: '<span  style="padding-left:18px;height:18px;" qtip="'+_('Acknowledge')+'" class="icon-16 icinga-icon-accept">'+_('Acknowledge')+'</span>',
                                name: 'type',
                                inputValue: 'type_1'
                            },{
                                boxLabel: '<span style="padding-left:18px;height:18px;" qtip="'+_('Revoke')+'" class="icon-16 icinga-icon-cancel">'+_('Revoke ack')+'</span>',
                                name: 'type',
                                inputValue: 'type_2'
                            }]
                        },{
                            xtype: 'textarea',
                            fieldLabel: _('Comment'),
                            name: 'message',
                            width: 460,
                            height: 130,
                            allowBlank: true
                        }],
                        buttons: [{
                            text: 'Submit',
                            handler: function() {
                                oForm = this.findParentByType(Ext.FormPanel);
                                if (oForm.getForm().isValid()) {
                                    var vals = oForm.getForm().getValues();
                                    vals.type = vals.type.split('_')[1];

                                    var events = [];
                                    var params = {};

                                    Ext.iterate(eventGrid.selectedRecords, function(r) {
                                        var ignored = [];
                                        if (vals.type == 0 ||
                                            (vals.type == 1 && r.get('ack') != 1) ||
                                            (vals.type == 2 && r.get('ack') == 1)) {

                                            events.push(r.get('id'));
                                        } else {
                                            ignored.push({
                                                host: r.get('host_name'),
                                                msg: r.get('message'),
                                                cr: r.get('created')
                                            });
                                        }
                                    });
                                    if(!this.ownerCt.ownerCt.ownerCt.forall) {
                                        var eventsJson = Ext.encode([Ext.apply(vals, {ids: events})]);
                                        eventGrid.unselectAll();
                                        oForm.getForm().submit({
                                            params: Ext.apply({'comments': eventsJson}, params),
                                            success: function(oForm, action) {
                                                // TODO: Reload only if selected.
                                                eventGrid.refresh();
                                                detailPanel.update();

                                            }
                                        });
                                    } else {
                                        var eventsJson = Ext.encode([Ext.apply(vals, {filter: eventGrid.getStore().baseParams.jsonFilter})]);
                                        eventGrid.unselectAll();
                                        oForm.getForm().submit({
                                            params: Ext.apply({'comments': eventsJson}, params),
                                            success: function(oForm, action) {
                                                // TODO: Reload only if selected.
                                                eventGrid.refresh();
                                                detailPanel.update();

                                            }
                                        });
                                    }
                                    oWin.hide();
                                }
                            }
                        },{
                            text: 'Close',
                            handler: function() {
                                oWin.hide();
                            }
                        }]
                    })
                });
                parentCmp.add(Ext.clean(oWin));
                parentCmp.doLayout();
            }
            oWin.forall = forall || false;
            oWin.show(this);
        }
    }
}