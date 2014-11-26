
local formatKey = function (key, suffix)
    local res = "Nette.Journal:" .. key
    if suffix ~= nil then
        res = res .. ":" .. suffix
    end

    return res
end

local formatStorageKey = function(key, suffix)
    local res = "Nette.Storage:" .. key
    if suffix ~= nil then
        res = res .. ":" .. suffix
    end

    return res
end

local priorityEntries = function (priority)
    return redis.call('zRangeByScore', formatKey("priority"), 0, priority)
end

local entryTags = function (key)
    return redis.call('sMembers', formatKey(key, "tags"))
end

local tagEntries = function (tag)
    return redis.call('sMembers', formatKey(tag, "keys"))
end

local range = function (from, to, step)
	step = step or 1
	local f =
		step > 0 and
			function(_, lastvalue)
				local nextvalue = lastvalue + step
				if nextvalue <= to then return nextvalue end
			end or
		step < 0 and
			function(_, lastvalue)
				local nextvalue = lastvalue + step
				if nextvalue >= to then return nextvalue end
			end or
			function(_, lastvalue) return lastvalue end
	return f, nil, from - step
end

local cleanEntry = function (keys)
    for i, key in pairs(keys) do
        local tags = entryTags(key)

        -- redis.call('multi')
        for i, tag in pairs(tags) do
            redis.call('sRem', formatKey(tag, "keys"), key)
        end

        -- drop tags of entry and priority, in case there are some
        redis.call('del', formatKey(key, "tags"), formatKey(key, "priority"))
        redis.call('zRem', formatKey("priority"), key)

        -- redis.call('exec')
    end
end

local mergeTables = function (first, second)
    for i, key in pairs(second) do
        first[#first + 1] = key
    end
    return first
end

local batch = function (keys, callback)
    if #keys > 0 then
        -- redis.call('multi')
        -- the magic number 7998 becomes from Lua limitations, see http://stackoverflow.com/questions/19202367/how-to-avoid-redis-calls-in-lua-script-limitations
        local tmp = {}
        for i,key in pairs(keys) do
            tmp[#tmp + 1] = key
            if #tmp >= 7998 then
                callback(tmp)
                tmp = {}
            end
        end
        callback(tmp)
        -- redis.call('exec')
    end
end

local batchDelete = function(keys)
    local delete = function (tmp)
        redis.call('del', unpack(tmp))
    end
    batch(keys, delete)
end
