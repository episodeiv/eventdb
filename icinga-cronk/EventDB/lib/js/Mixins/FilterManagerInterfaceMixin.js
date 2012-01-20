
Ext.ns("Cronk.EventDB.Mixins").FilterManagerInterfaceMixin = function() {
    var getDefaultFilter = function() {
        return {
			hostFilter: {
				include_pattern: false,
				include_pattern_type: 'disabled',
				exclude_pattern_type: 'disabled',
				exclude_pattern: false,
				include_set: [],
				exclude_set: []
			},
			programFilter: {
				include_pattern: false,
				include_pattern_type: 'disabled',
				exclude_pattern: false,
				exclude_pattern_type: 'disabled',
				include_set: [],
				exclude_set: []
			},
			messageFilter: {
				items: []
			},
			misc: {
				hideAck: false
			},
			sourceExclusion: [],
			priorityExclusion: [],
			facilityExclusion: [],
			timespan: {
				from: -1,
				to: -1
			},
			display: {
				order: {
					field: 'created',
					dir: 'desc'
				},
				group: {
					field: null
				},
				count: 'id',
				limit: 25
			}
		}
    }

    this.currentFilterObject = getDefaultFilter(); // statically set to default filter at first time

    this.getFilterObject = function() {
        return this.currentFilterObject;
    }

    this.resetFilterObject = function() {
        this.currentFilterObject = this.getDefaultFilter();
    }

    this.hasActiveFilter = function() {
		if(this.getHostIncludePatternMatchType() != 'disabled')
			return true;
        if(this.getHostExcludePatternMatchType() != 'disabled')
			return true;
		if(this.getProgramIncludePatternMatchType() != 'disabled')
			return true;
        if(this.getProgramExcludePatternMatchType() != 'disabled')
			return true;

		if(this.getMessageFilter().length > 0)
			return true;
		if(this.getExcludedEventSources().length > 0)
			return true;
        if(this.getExcludedPriorities().length > 0)
            return true;
        if(this.getExcludedFacilities().length >0)
			return true;
        if(this.showsAcknowledged())
            return false;
        if(this.getFromTime() > -1 || this.getToTime() > 1)
            return true;

        return false;
    }

    this.getDefaultFilter = function() {
        return getDefaultFilter();
    }

    this.isValidDescriptor = function(descriptor) {
        var template = this.getDefaultFilter();
        // fn is references to the function object itself
        function checkTpl(cmp1,cmp2,fn) {
            for(var i in cmp1) {
                // check if all keys from template exist
                if(typeof cmp2[i] === "undefined") {
                    return false;
                }
                // traverse down the tree
                if(Ext.isObject(cmp1[i] == "object"))
                    return fn(cmp1[i],cmp2[i],fn);
            }
            return true;
        }
        return checkTpl(descriptor,template,checkTpl);
    }

    /**
     * Function to set raw filter descriptor
     */
    this.setFilterObject = function(descriptor, apply) {
        if(apply === true) {
            this.currentFilterObject = Ext.apply(this.currentFilterObject,descriptor);
            return true;
        }

        if(this.isValidDescriptor(descriptor)) {
            this.currentFilterObject = descriptor;
            return true;
        } else {
            AppKit.log("Invalid filter descriptor provided", descriptor);
            return false
        }
        
    }

    

    this.genericToggle = function(target,value,state) {
        var exists = target.indexOf(value);
        
        if(exists > -1 && (typeof state === "undefined" || state === true)) {
            
            target.remove(value);
        } else if (exists === -1 && (typeof state === "undefined" || state === false)) {
            
            target.push(value);
        }
    }

    this.toggleFacility = function(facility, state) {
        this.genericToggle(this.currentFilterObject.facilityExclusion,facility,state);
    };

    this.getExcludedFacilities = function() {
        return this.currentFilterObject.facilityExclusion;
    };

    this.togglePriority = function(priority, state) {
        this.genericToggle(this.currentFilterObject.priorityExclusion,priority,state);
    }

    this.getExcludedPriorities = function() {
        return this.currentFilterObject.priorityExclusion;
    }

    this.toggleEventSource = function(source, state) {
        this.genericToggle(this.currentFilterObject.sourceExclusion,source,state);
    }

    this.getExcludedEventSources = function() {
        return this.currentFilterObject.sourceExclusion;
    }

	this.toggleAcknowledged = function(show) {
        if(typeof show === "undefined")
            this.currentFilterObject.misc.hideAck = !this.currentFilterObject.misc.hideAck;
        else
            this.currentFilterObject.misc.hideAck = !show;
    }

    this.showsAcknowledged = function() {
        return !this.hideAck;
    }

    this.setFromTime = function(time) {
        this.currentFilterObject.timespan.from = time;
    }

    this.getFromTime = function() {
        return this.currentFilterObject.timespan.from;
    }

    this.setToTime = function(time) {
        this.currentFilterObject.timespan.to = time;
    }

    this.getToTime = function() {
        return this.currentFilterObject.timepan.to;
    }

	this.setHostIncludes = function(includeArray) {
        this.currentFilterObject.hostFilter.include_set = includeArray;
    }

    this.getHostIncludes = function() {
        return this.currentFilterObject.hostFilter.include_set;
    }

    this.setHostExcludes = function(excludeArray) {
        this.currentFilterObject.hostFilter.exclude_set = excludeArray;
    }

    this.getHostExcludes = function() {
        return this.currentFilterObject.hostFilter.exclude_set;
    }

    this.setHostIncludePattern = function(pattern,matchType) {
        this.currentFilterObject.hostFilter.include_pattern = pattern;
        this.currentFilterObject.hostFilter.include_pattern_type = matchType;
    }

    this.getHostIncludePattern = function() {
        return this.currentFilterObject.hostFilter.include_pattern;
    }

    this.getHostIncludePatternMatchType = function() {
        return this.currentFilterObject.hostFilter.include_pattern_type;
    }

    this.setHostExcludePattern = function(pattern,matchType) {
        this.currentFilterObject.hostFilter.exclude_pattern = pattern;
        this.currentFilterObject.hostFilter.exclude_pattern_type = matchType;
    }

    this.getHostExcludePattern = function() {
        return this.currentFilterObject.hostFilter.exclude_pattern;
    }

    this.getHostExcludePatternMatchType = function() {
        return this.currentFilterObject.hostFilter.exclude_pattern_type;
    }

    this.setProgramIncludes = function(includeArray) {
        this.currentFilterObject.programFilter.include_set = includeArray;
    }

    this.getProgramIncludes = function() {
        return this.currentFilterObject.programFilter.include_set;
    }

    this.setProgramExcludes = function(excludeArray) {
        this.currentFilterObject.programFilter.exclude_set = excludeArray;
    }

    this.getProgramExcludes = function() {
        return this.currentFilterObject.programFilter.exclude_set;
    }

    this.setProgramIncludePattern = function(pattern,matchType) {
        this.currentFilterObject.programFilter.include_pattern = pattern;
        this.currentFilterObject.programFilter.include_pattern_type = matchType;
    }

    this.getProgramIncludePattern = function() {
        return this.currentFilterObject.programFilter.include_pattern;
    }

    this.getProgramIncludePatternMatchType = function() {
        return this.currentFilterObject.programFilter.include_pattern_type;
    }

    this.setProgramExcludePattern = function(pattern,matchType) {
        this.currentFilterObject.programFilter.exclude_pattern = pattern;
        this.currentFilterObject.programFilter.exclude_pattern_type = matchType;
    }

    this.getProgramExcludePattern = function() {
        return this.currentFilterObject.programFilter.exclude_pattern;
    }

    this.getProgramExcludePatternMatchType = function() {
        return this.currentFilterObject.programFilter.exclude_pattern_type;
    }

    this.setMessageFilter = function(messageFilterArray) {
        
        this.currentFilterObject.messageFilter.items.push(messageFilterArray);
    }

    this.getMessageFilter = function() {
        return this.currentFilterObject.messageFilter.items;
    }

   	this.setDisplayLimit = function(limit) {
        if(!isNaN(limit))
            this.currentFilterObject.display.limit = limit;
        else
            AppKit.log("Limit is NaN in Eventdb");
    }

    this.getDisplayLimit = function() {
        return this.currentFilterObject.display.limit;
    }

    this.setDisplayOrderColumn = function(orderColumn) {
        this.currentFilterObject.display.order.field = orderColumn;
    }

    this.getDisplayOrderColumn = function() {
        return this.currentFilterObject.display.order.field;
    }

    this.setDisplayDir = function(ascOrDesc) {
        this.currentFilterObject.display.order.dir = ascOrDesc.toLowerCase();
    }
    
    this.getDisplayDir = function() {
        return this.currentFilterObject.display.order.dir;
    }

    this.setGroupBy = function(groupColumn) {
        this.currentFilterObject.display.group.field = groupColumn;
    }

    this.getGroupBy = function() {
        return this.currentFilterObject.display.group.field;
    }

}
