Ext.ns('Cronk.EventDB.FilterManagerViews').AdditionalFields = function (additionalFields) {

    function createPatternField(header, dataIndex, includeOrExclude) {
        includeOrExclude = includeOrExclude.toLowerCase();
        var ucfirst = includeOrExclude.charAt(0).toUpperCase() + includeOrExclude.slice(1);
        return {
            xtype: 'container',
            layout: 'column',
            items: [
                {
                    xtype: 'container',
                    layout: 'form',
                    items: [
                        {
                            xtype: 'textfield',
                            fieldLabel: ucfirst,
                            name: dataIndex + '_' +  includeOrExclude + '_pattern',
                            ref: '../' + includeOrExclude + 'PatternField',
                            disabled: true,
                            reset: function () {
                                this.setValue(this.initialConfig.value);
                            }
                        }
                    ]
                },
                {
                    xtype: 'cycle',
                    showText: true,
                    name: dataIndex + '_' +  includeOrExclude + '_pattern_type',
                    items: [
                        {
                            selected: true,
                            text: _('Disabled'),
                            value: 'disabled'
                        },
                        {
                            text: _('Exact'),
                            value: 'exact'
                        },
                        {
                            text: _('Contains'),
                            value: 'contains'
                        },
                        {
                            text: _('RegExp'),
                            value: 'regexp'
                        }
                    ],
                    changeHandler: function (button, item) {
                        if (item.value === 'disabled') {
                            button.ownerCt[includeOrExclude + 'PatternField'].disable();
                        } else {
                            button.ownerCt[includeOrExclude + 'PatternField'].enable();
                        }
                    },
                    ref: 'patternStateButton',
                    setValue: function (value) {
                        this.setActiveItem(this.menu.items.itemAt(this.menu.items.findIndex('value', value)));
                    },
                    getValue: function () {
                        return this.getActiveItem().value;
                    },
                    reset: function () {
                        this.setValue('disabled');
                    }
                }
            ]
        }
    }

    function createGrid (header, dataIndex, includeOrExclude) {
        includeOrExclude = includeOrExclude.toLowerCase();
        var ucfirst = includeOrExclude.charAt(0).toUpperCase() + includeOrExclude.slice(1);
        return {
            name: dataIndex + '_' + includeOrExclude + '_set',
            cm: new Ext.grid.ColumnModel({
                defaults: {
                    sortable: true // Columns are not sortable by default
                },
                columns: [{
                    id: dataIndex,
                    header: header,
                    dataIndex: dataIndex,
                    editor: new Ext.form.TextField({
                        allowBlank: false
                    })
                }]
            }),
            sm: new Ext.grid.RowSelectionModel(),
            title: (includeOrExclude === 'include' ? 'Addtional' : 'Excluded') + ' ' + header,
            store: {
                xtype: 'arraystore',
                autoDestroy: true,
                idIndex: 0,
                fields: [dataIndex]
            },
            getValue: function () {
                return this.store.collect(dataIndex);
            },
            setValue: function (value) {
                for (var i = 0; i < value.length; ++i) {
                    value[i] = [value[i]];
                }
                this.store.loadData(value);
            },
            reset: function () {
                this.setValue(this.initialConfig.store.data || []);
            }
        }
    }

    var columns = [];

    for (var i = 0; i < additionalFields.length; ++i) {
        var header = additionalFields[i].header,
            dataIndex = additionalFields[i].dataIndex;
        var columnConfig = {
            xtype: 'container',
            width: 400,
            items: [
                {
                    xtype: 'panel',
                    height: 80,
                    title: header,
                    items: [
                        createPatternField(header, dataIndex, 'include'),
                        createPatternField(header, dataIndex, 'exclude')
                    ]
                },
                {
                    xtype: 'container',
                    layout: 'column',
                    defaults: {
                        height: 150,
                        columnWidth: .5,
                        xtype: 'editorgrid',
                        viewConfig: {
                            forceFit: true
                        },
                        tools: [
                            {
                                id: 'plus',
                                handler: function (event, toolEl, grid) {
                                    var store = grid.getStore(),
                                        record = new store.recordType();
                                    grid.stopEditing();
                                    store.insert(0, record);
                                    grid.startEditing(0, 0);
                                }
                            },
                            {
                                id: 'minus',
                                handler: function (event, toolEl, grid) {
                                    grid.getStore().remove(grid.getSelectionModel().getSelections());
                                }
                            }
                        ]
                    },
                    items: [
                        createGrid(header, dataIndex, 'include'),
                        createGrid(header, dataIndex, 'exclude')
                    ]
                }
            ]
        };

        columns.push(columnConfig);
    }

    return new (Ext.extend(Ext.FormPanel, {
        // Extend
        updateFields: function (filter) {
            if (JSON.stringify(filter.additionalFields) !== '{}') {
                var cmpValues = {};
                for (var dataIndex in filter.additionalFields) {
                    if (filter.additionalFields.hasOwnProperty(dataIndex)) {
                        for (var cmpNameSuffix in filter.additionalFields[dataIndex]) {
                            if (filter.additionalFields[dataIndex].hasOwnProperty(cmpNameSuffix)) {
                                cmpValues[dataIndex + '_' + cmpNameSuffix] = filter.additionalFields[dataIndex][cmpNameSuffix];
                            }
                        }
                    }
                }
                this.cascade(function (cmp) {
                    if (cmp.name) {
                        console.log(cmp.name, cmpValues, cmpValues.hasOwnProperty(cmp.name));
                        if (cmpValues.hasOwnProperty(cmp.name)) {
                            cmp.setValue(cmpValues[cmp.name]);
                        }
                    }
                });
            }
        }
    }))({
        // Invoke
        title: 'Additional Fields',
        layout: 'table',
        layoutConfig:{
            columns: 2
        },
        items: columns
    });
};
