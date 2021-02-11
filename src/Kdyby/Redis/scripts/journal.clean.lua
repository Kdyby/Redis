local formatKey = function (key, suffix)
    local res = "Nette.Journal:" .. key
    if suffix ~= nil then
        res = res .. ":" .. suffix
    end

    return res
end

local entryTags = function (key)
    return redis.call('sMembers', formatKey(key, "tags"))
end

local tagEntries = function (tag)
    return redis.call('sMembers', formatKey(tag, "keys"))
end

local conds = cjson.decode(ARGV[1])

if conds["tags"] ~= nil then
    for i, tag in pairs(conds["tags"]) do
        local found = tagEntries(tag)

        if #found > 0 then
            for i, key in pairs(found) do
                local tags = entryTags(key)
                -- redis.call('multi')
                for i, tag in pairs(tags) do
                    redis.call('sRem', formatKey(tag, "keys"), key)
                end
                -- drop tags of entry and priority, in case there are some
                redis.call('del', formatKey(key, "tags"))

                redis.call("del", key)
            end
        end
    end
end

return redis.status_reply("Ok")