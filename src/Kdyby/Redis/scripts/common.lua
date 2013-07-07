
local formatKey = function (key, suffix)
    local res = "Nette.Journal:" .. key:gsub("\x00", ":")
    if suffix ~= nil then
        res = res .. ":" .. suffix
    end

    return res
end

local priorityEntries = function (priority)
    return redis.call('zRangeByScore', formatKey("priority"), 0, priority)
end

local entryTags = function (key)
    return redis.call('lRange', formatKey(key, "tags"), 0, -1)
end

local tagEntries = function (tag)
    return redis.call('lRange', formatKey(tag, "keys"), 0, -1)
end

local cleanEntry = function (keys)
    for i, key in pairs(keys) do
        local tags = entryTags(key)

        -- redis.call('multi')
        for i, tag in pairs(tags) do
            redis.call('lRem', formatKey(tag, "keys"), 0, key)
        end

        -- drop tags of entry and priority, in case there are some
        redis.call('del', formatKey(key, "tags"), formatKey(key, "priority"))
        redis.call('zRem', formatKey("priority"), key)

        -- redis.call('exec')
    end
end

-- builds table from serialized arguments
local readArgs = function (args)
    local res = {}
    local counter = 0
    local key
    local tmp

    for i, item in pairs(args) do
        if counter > 0 then
            if res[key] == nil then res[key] = {} end

            tmp = res[key]
            res[key][#tmp + 1] = item
            counter = counter - 1

            if counter == 0 then key = nil end

        elseif counter < 0 then
            res[key] = item
            key = nil

        else
            if key == nil then key = item else counter = tonumber(item); end
        end
    end

    return res
end
