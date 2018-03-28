enyo.kind({
    name: 'BibleManager.Components.Grid',

    components: [
        {name: 'Grid', tag: 'table'},
        {name: 'GridFooter'}
    ],

    selectedIds: [],
    gridHandle: null,
    idPrefix: 'bible_',

    rendered: function() {
        this.inherited(arguments);

        if(this.$.Grid.hasNode() && this.gridHandle == null) {
            var pagerId = '#' + this.$.GridFooter.get('id');

            this.gridHandle = $(this.$.Grid.hasNode()).jqGrid({
                url: '/admin/bibles/grid',
                datatype: 'json',
                idPrefix: this.idPrefix,
                colModel: [
                    {name: 'name', index: 'name', label: 'Name', width:'300', editable: true},
                    {name: 'shortname', index: 'shortname', label: 'Short Name', width:'100', editable: true},
                    {name: 'module', index: 'module', label: 'Module', width:'100'},
                    {name: 'has_module_file', index: 'has_module_file', label: 'Has File', width:'140', title: false, sortable: false, formatter: enyo.bind(this, this._formatHasFile)}, // will be sortable when grid is using local data
                    {name: 'lang', index: 'lang', label: 'Language', width:'100'},
                    {name: 'year', index: 'year', label: 'Year', width:'100'},

                    {name: 'installed', index: 'installed', label: 'Installed', width:'80', title: false, formatter: enyo.bind(this, this._formatInstalled)},
                    {name: 'enabled', index: 'enabled', label: 'Enabled', width:'80', title: false, formatter: enyo.bind(this, this._formatEnabled)},
                    {name: 'official', index: 'official', label: 'Official', width:'60', title: false, formatter: enyo.bind(this, this._formatSinpleBoolean)},
                    {name: 'rank', index: 'rank', label: 'Display Order', width:'100', editable: true, edittype: 'text', editoptions: {size:10, maxlength: 15}},
                    {name: 'actions', index: 'actions', label: '&nbsp', width:'100'},
                ],
                jsonReader: {
                    repeatitems: false,
                    id: 'id'
                },
                pager: pagerId,
                sortname: 'name',
                sortorder: 'asc',
                viewrecords: true,
                height: 'auto',
                width: 'auto',
                multiselect: true,
                rowNum: 15,
                rowList: [10, 15, 20, 30],
                onSelectRow: enyo.bind(this, this._selectRow),
                onSelectAll: enyo.bind(this, this._selectRow)
            });

            this.gridHandle.navGrid(pagerId, {search: false, edit: false, view: false, del: false, add: false, refresh: true, nav: {

            }}, {}, {}, {}, {}, {});

            $( ".button" ).button();
        }
    },

    refreshGrid: function() {
        this.gridHandle && this.gridHandle.trigger('reloadGrid');
    },
    getRowByPk: function(pk) {
        var id = this.idPrefix + pk.toString();
        return this.getRowById(id);
    },
    getRowById: function(id) {
        return this.gridHandle ? this.gridHandle.jqGrid('getRowData', id) : null;
    },
    _selectRow: function(rowId, status, e) {
        this.log('rowId', rowId);
        this.log('status', status);
        this.log('e', e);
        this.set('selectedIds', enyo.clone(this.gridHandle.getGridParam('selarrrow')));
    },

    selectedIdsChanged: function(was, is) {
        this.log(is);
        // this.$.BulkActions.set('showing', (is.length) ? true : false);
    },

    __makeSignalUrl: function(signal, props) {
        var propsJson = JSON.stringify(props);
        var url = 'enyo.Signals.send("' + signal + '",' + propsJson + ')';
        return url;
    },

    __setCellColor: function(rowId, cellIndex, color) {
        // this.gridHandle && this.gridHandle.
    },

    _formatSinpleBoolean: function(cellvalue, options, rowObject) {
        return (cellvalue == '1') ? 'Yes' : 'No&nbsp;';
    },
    _formatInstalled: function(cellvalue, options, rowObject) {
        var fmt = (cellvalue == '1') ? 'Yes' : 'No&nbsp;';
        options.colModel.classes = (cellvalue == '1') ? 'on' : 'off';
        
        if(cellvalue == '1' || rowObject.has_module_file == '1') {        
            var action = (cellvalue == '1') ? 'Uninstall' : 'Install';
            var signal = (cellvalue == '1') ? 'onConfirmAction' : 'onBibleInstall';

            var props = (cellvalue == '1') ? {id: options.rowId, action: 'uninstall'} : {id: options.rowId};
            var url = this.__makeSignalUrl(signal, props);
            fmt += " &nbsp; &nbsp;<a href='javascript:" + url + "'>" + action + "</a>";
        }
        return fmt;
    },     
    _formatHasFile: function(cellvalue, options, rowObject) {
        var fmt = (cellvalue == '1') ? 'Yes' : 'No&nbsp;';
        options.colModel.classes = (cellvalue == '1') ? '' : 'alert';
        
        if(rowObject.installed == '1') {        
            var props = {id: options.rowId};
            var url = this.__makeSignalUrl('onBibleExport', props);
            fmt += " &nbsp; &nbsp;<a href='javascript:" + url + "'>Export Module File</a>";
        }

        return fmt;
    },    
    _formatEnabled: function(cellvalue, options, rowObject) {
        // console.log('cellvalue', cellvalue);
        // console.log('options', options);
        // console.log('rowObject', rowObject);

        var fmt = (cellvalue == '1') ? 'Yes' : 'No&nbsp;';
        options.colModel.classes = (cellvalue == '1') ? 'on' : 'off';

        if(rowObject.installed == '1') {        
            var text = (cellvalue == '1') ? 'Disable' : 'Enable';
            var action = (cellvalue == '1') ? 'disable' : 'enable';
            var signal = (cellvalue == '1') ? 'onBibleDisable' : 'onBibleEnable';
            var props = {id: options.rowId, action: action};
            var url = this.__makeSignalUrl('onConfirmAction', props);
            fmt += " &nbsp; &nbsp;<a href='javascript:" + url + "'>" + text + "</a>";
        }

        return fmt;
    },
});