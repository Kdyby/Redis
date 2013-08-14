
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
