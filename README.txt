Analyse get_string calls tool

This is a developer tool that analyses all the calls to get string. This should
let you detect unused strings, or strings that are in the wrong plugin, etc.

It was created by Tim Hunt at the Open University.

To install using git, type thse commands in the root of your Moodle install
    git clone git://github.com/timhunt/moodle-tool_analysegetstring.git admin/tool/analysegetstring
    echo '/admin/tool/analysegetstring/' >> .git/ignore

You must also have https://github.com/timhunt/moodle-local_codechecker/ installed.

After you have installed this local plugin , you should see a new option 
Site administration -> Development -> Analyse get_string calls in the settings block.

I hope you find this tool useful. Please feel free to enhance it.


Known limitations

1. Does not find strings used in JavaScript.
2. Does not always work out the right component for files from core components.
3. Does not correctly analyse which help strings are used.


What you can do with the data

The script populates the {tool_analysegetstring_string} and
{tool_analysegetstring_calls} database tables, so once you have run the analysis
you can do queries like:

SELECT s.component, s.identifier
FROM mdl_tool_analysegetstring_string s
WHERE (s.component LIKE 'quiz%' OR s.component = 'mod_quiz')
AND NOT EXISTS (
    SELECT 1
    FROM mdl_tool_analysegetstring_calls c
    WHERE c.identifier = s.identifier
    AND c.stringcomponent = s.component
)
AND s.identifier <> 'pluginname'

which tries to find unused strings in the quiz; or

SELECT DISTINCT stringcomponent
FROM mdl_tool_analysegetstring_calls
WHERE stringcomponent LIKE 'EXP: %'

which finds all the places where a call to get_string used an expression instead
of a fixed $component string argument; or

SELECT DISTINCT stringcomponent, identifier
FROM q_tool_analysegetstring_calls
WHERE stringcomponent <> sourcecomponent

which finds all strings referred to from outside their owning plugin.
