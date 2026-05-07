(function (blocks, element, components, blockEditor, apiFetch) {
    var el = element.createElement;
    var useEffect = element.useEffect;
    var useState = element.useState;
    var SelectControl = components.SelectControl;
    var Button = components.Button;
    var Notice = components.Notice;
    var Spinner = components.Spinner;
    var PanelBody = components.PanelBody;
    var InspectorControls = blockEditor.InspectorControls;
    var useBlockProps = blockEditor.useBlockProps;

    function MatchPreview(props) {
        if (!props.item) {
            return null;
        }

        return el('div', { className: 'fftt-match-block preview' }, [
            el('div', { className: 'fftt-match-score' }, [
                el('span', { className: 'fftt-team' }, props.item.teamA),
                el('strong', { className: 'fftt-score' }, String(props.item.scoreA) + ' - ' + String(props.item.scoreB)),
                el('span', { className: 'fftt-team' }, props.item.teamB)
            ])
        ]);
    }

    blocks.registerBlockType('fftt/match', {
        title: 'FFTT Match',
        icon: 'table-col-after',
        category: 'widgets',
        attributes: {
            teamId: { type: 'number', default: 0 },
            teamName: { type: 'string', default: '' },
            matchLink: { type: 'string', default: '' },
            matchLabel: { type: 'string', default: '' },
            matchClubA: { type: 'string', default: '' },
            matchClubB: { type: 'string', default: '' },
            teamA: { type: 'string', default: '' },
            teamB: { type: 'string', default: '' },
            scoreA: { type: 'number', default: 0 },
            scoreB: { type: 'number', default: 0 }
        },

        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps({ className: 'fftt-match-block-editor' });

            var teams = useState([]);
            var matches = useState([]);
            var isLoadingTeams = useState(true);
            var isLoadingMatches = useState(false);
            var error = useState('');

            var teamsValue = teams[0];
            var setTeams = teams[1];
            var matchesValue = matches[0];
            var setMatches = matches[1];
            var loadingTeamsValue = isLoadingTeams[0];
            var setLoadingTeams = isLoadingTeams[1];
            var loadingMatchesValue = isLoadingMatches[0];
            var setLoadingMatches = isLoadingMatches[1];
            var errorValue = error[0];
            var setError = error[1];

            function loadTeams() {
                setLoadingTeams(true);
                setError('');
                apiFetch({ path: '/fftt-match/v1/teams' }).then(function (response) {
                    setTeams(response.items || []);
                    setLoadingTeams(false);
                }).catch(function (err) {
                    setError(err && err.message ? err.message : 'Erreur lors du chargement des equipes.');
                    setTeams([]);
                    setLoadingTeams(false);
                });
            }

            function loadMatches(teamId) {
                if (!teamId) {
                    setMatches([]);
                    return;
                }

                setLoadingMatches(true);
                setError('');

                var path = '/fftt-match/v1/matches?teamId=' + encodeURIComponent(String(teamId));

                apiFetch({ path: path }).then(function (response) {
                    setMatches(Array.isArray(response.items) ? response.items.slice() : []);
                    setLoadingMatches(false);
                }).catch(function (err) {
                    setError(err && err.message ? err.message : 'Erreur lors du chargement des matchs.');
                    setMatches([]);
                    setLoadingMatches(false);
                });
            }

            useEffect(function () {
                loadTeams();
            }, []);

            useEffect(function () {
                loadMatches(attributes.teamId || 0);
            }, [attributes.teamId]);

            function getPreviewItem() {
                var i;

                for (i = 0; i < matchesValue.length; i += 1) {
                    if (matchesValue[i].lien === attributes.matchLink) {
                        return matchesValue[i];
                    }
                }

                if (attributes.teamA && attributes.teamB) {
                    return {
                        teamA: attributes.teamA,
                        teamB: attributes.teamB,
                        scoreA: attributes.scoreA,
                        scoreB: attributes.scoreB
                    };
                }

                return null;
            }

            var teamOptions = [{ label: 'Selectionner une equipe', value: 0 }].concat(
                teamsValue.map(function (item) {
                    return {
                        label: item.name,
                        value: item.id
                    };
                })
            );

            var options = [{ label: 'Selectionner un match', value: '' }].concat(
                matchesValue.map(function (item) {
                    return {
                        label: item.label,
                        value: item.lien
                    };
                })
            );

            return el('div', blockProps, [
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Selection du match', initialOpen: true }, [
                        el(SelectControl, {
                            label: 'Equipe FFTT',
                            value: attributes.teamId || 0,
                            options: teamOptions,
                            onChange: function (value) {
                                var nextTeamId = Number(value) || 0;
                                var nextTeamName = '';
                                for (var t = 0; t < teamsValue.length; t += 1) {
                                    if (Number(teamsValue[t].id) === nextTeamId) {
                                        nextTeamName = teamsValue[t].name;
                                        break;
                                    }
                                }

                                setAttributes({
                                    teamId: nextTeamId,
                                    teamName: nextTeamName,
                                    matchLink: '',
                                    matchLabel: '',
                                    matchClubA: '',
                                    matchClubB: '',
                                    teamA: '',
                                    teamB: '',
                                    scoreA: 0,
                                    scoreB: 0
                                });
                            }
                        }),
                        el(SelectControl, {
                            label: 'Match FFTT',
                            value: attributes.matchLink,
                            options: options,
                            onChange: function (value) {
                                var label = '';
                                var clubA = '';
                                var clubB = '';
                                var teamA = '';
                                var teamB = '';
                                var scoreA = 0;
                                var scoreB = 0;
                                for (var i = 0; i < matchesValue.length; i += 1) {
                                    if (matchesValue[i].lien === value) {
                                        label = matchesValue[i].label;
                                        clubA = String(matchesValue[i].clubA || '');
                                        clubB = String(matchesValue[i].clubB || '');
                                        teamA = String(matchesValue[i].teamA || '');
                                        teamB = String(matchesValue[i].teamB || '');
                                        scoreA = Number(matchesValue[i].scoreA || 0);
                                        scoreB = Number(matchesValue[i].scoreB || 0);
                                        break;
                                    }
                                }
                                setAttributes({
                                    matchLink: value,
                                    matchLabel: label,
                                    matchClubA: clubA,
                                    matchClubB: clubB,
                                    teamA: teamA,
                                    teamB: teamB,
                                    scoreA: scoreA,
                                    scoreB: scoreB
                                });
                            }
                        }),
                        el(Button, {
                            variant: 'secondary',
                            onClick: function () {
                                loadTeams();
                                loadMatches(attributes.teamId || 0);
                            }
                        }, 'Rafraichir equipes et matchs')
                    ])
                ),
                loadingTeamsValue ? el(Spinner) : null,
                loadingMatchesValue ? el(Spinner) : null,
                errorValue ? el(Notice, { status: 'error', isDismissible: false }, errorValue) : null,
                !attributes.teamId ? el('div', { className: 'fftt-match-block-placeholder' }, 'Choisis une equipe dans la colonne de droite.') : null,
                attributes.teamId && !attributes.matchLink ? el('div', { className: 'fftt-match-block-placeholder' }, 'Choisis un match dans la colonne de droite.') : null,
                el(MatchPreview, { item: getPreviewItem() })
            ]);
        },

        save: function () {
            return null;
        }
    });
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.apiFetch);
