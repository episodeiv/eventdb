
Ext.ns('Cronk.EventDB.FilterManagerViews').Advanced = function(url) {
    var handler = {
        addPopup: function(field,targetStore,excludeStore) {
            
            var store = new Ext.data.JsonStore({
                url: url,
                root: 'events',
                fields: [
                {
                    name: field
                }
                ],
                baseParams: {

                    'columns[0]' : field,
                    'count' : field,
                    'group_by': field,
                    'simple' :true
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
            
            var pBar = new Cronk.EventDB.Components.OptimisticPagingToolbar({
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
            
            var tBar = [
            'Contains'
            ,{
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
                        header: field, 
                        dataIndex: field
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
            var presets = values || {};

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
                set = set || [];

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
                columns: [{
                    header: name,
                    dataIndex:fieldname
                }],
                viewConfig: {
                    forceFit: true
                }
            });
            var excgrid = new Ext.grid.GridPanel({
                store: excstore,
                columns: [{
                    header: name,
                    dataIndex:fieldname
                }],
                viewConfig: {
                    forceFit: true
                }
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
                    if(data.length > 0)    {
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
                    handler: function() {
                        handler.addPopup.call(this,fieldname,incstore,excstore)
                        },
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
                            width:75,
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
                            target = {
                                p: hostPatternField, 
                                i:hostIncludeExcludeField
                            }
                        else
                            target = {
                                p: programPatternField, 
                                i:programIncludeExcludeField
                            }
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
                items:     [{
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
                items:     [{
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